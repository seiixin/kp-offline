import React, { useEffect, useMemo, useState } from "react";

/* =========================
   CSRF / XSRF HELPERS
========================= */
function getMetaCsrf() {
  const el = document.querySelector('meta[name="csrf-token"]');
  return el ? el.getAttribute("content") : "";
}

function getCookie(name) {
  const value = `; ${document.cookie}`;
  const parts = value.split(`; ${name}=`);
  if (parts.length === 2) return parts.pop().split(";").shift();
  return "";
}

function getXsrfFromCookie() {
  const raw = getCookie("XSRF-TOKEN");
  return raw ? decodeURIComponent(raw) : "";
}

/* =========================
   COMPONENT
========================= */
export default function NewRechargeModal({ open, onClose, onCreated }) {
  const [form, setForm] = useState({
    mongo_user_id: "",
    coins_amount: "",
    method: "GCash",
    reference: "",
    proof_url: "",
    notes: "",
  });

  /* ---------- dropdown state ---------- */
  const [userQuery, setUserQuery] = useState("");
  const [userOptions, setUserOptions] = useState([]);
  const [loadingUsers, setLoadingUsers] = useState(false);

  /* ---------- ui state ---------- */
  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState("");
  const [okMsg, setOkMsg] = useState("");

  /* =========================
     FETCH USERS (DROPDOWN)
  ========================= */
  useEffect(() => {
    if (!open) return;

    const controller = new AbortController();
    setLoadingUsers(true);

    fetch(`/agent/users/dropdown?q=${encodeURIComponent(userQuery)}`, {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      credentials: "same-origin",
      signal: controller.signal,
    })
      .then((r) => r.json())
      .then((j) => setUserOptions(j?.data || []))
      .catch(() => {})
      .finally(() => setLoadingUsers(false));

    return () => controller.abort();
  }, [userQuery, open]);

  /* =========================
     VALIDATION
  ========================= */
  const canSubmit = useMemo(() => {
    return (
      String(form.mongo_user_id || "").length === 24 &&
      Number(form.coins_amount || 0) > 0 &&
      String(form.method || "").length > 0
    );
  }, [form]);

  /* =========================
     SUBMIT
  ========================= */
  const submit = async () => {
    setErr("");
    setOkMsg("");
    setLoading(true);

    const csrf = getMetaCsrf();
    const xsrf = getXsrfFromCookie();

    try {
      const res = await fetch(route("console.agent.recharges.store"), {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
          ...(csrf ? { "X-CSRF-TOKEN": csrf } : {}),
          ...(xsrf ? { "X-XSRF-TOKEN": xsrf } : {}),
        },
        credentials: "same-origin",
        body: JSON.stringify({
          ...form,
          coins_amount: Number(form.coins_amount),
          reference: form.reference || null,
          proof_url: form.proof_url || null,
          notes: form.notes || null,
        }),
      });

      const ct = res.headers.get("content-type") || "";
      const data = ct.includes("application/json")
        ? await res.json()
        : { error: await res.text() };

      if (!res.ok) {
        throw new Error(data?.error || "Failed to create recharge.");
      }

      setOkMsg(
        data?.already_processed
          ? "Already processed (idempotency)."
          : "Recharge created successfully."
      );

      setForm({
        mongo_user_id: "",
        coins_amount: "",
        method: "GCash",
        reference: "",
        proof_url: "",
        notes: "",
      });

      onCreated?.();
    } catch (e) {
      setErr(e.message);
    } finally {
      setLoading(false);
    }
  };

  if (!open) return null;

  /* =========================
     UI
  ========================= */
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-3 py-6">
      <div className="w-full max-w-lg rounded-2xl border border-white/10 bg-[#0b0f1a] p-4 shadow-2xl">
        {/* HEADER */}
        <div className="flex items-start justify-between">
          <div className="text-[13px] font-semibold text-white/90">
            New offline recharge
          </div>
          <button
            className="rounded-lg border border-white/10 bg-white/5 px-2 py-1 text-[12px] text-white/70 hover:bg-white/10"
            onClick={onClose}
            disabled={loading}
          >
            Close
          </button>
        </div>

        {/* BODY */}
        <div className="mt-4 grid gap-3">
          {/* USER DROPDOWN */}
          <div>
            <div className="mb-1 text-[11px] font-semibold text-white/70">
              Player
            </div>

            <input
              className="mb-1 w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-[12px] text-white"
              placeholder="Search name or username…"
              value={userQuery}
              onChange={(e) => setUserQuery(e.target.value)}
            />

            <select
              className="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-[12px] text-white"
              value={form.mongo_user_id}
              onChange={(e) =>
                setForm((p) => ({ ...p, mongo_user_id: e.target.value }))
              }
            >
              <option value="">
                {loadingUsers ? "Loading users…" : "Select user"}
              </option>
              {userOptions.map((u) => (
                <option key={u.value} value={u.value}>
                  {u.label}
                </option>
              ))}
            </select>
          </div>

          {/* COINS + METHOD */}
          <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
              <div className="mb-1 text-[11px] font-semibold text-white/70">
                Coins amount
              </div>
              <input
                type="number"
                min="1"
                className="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-[12px] text-white"
                value={form.coins_amount}
                onChange={(e) =>
                  setForm((p) => ({ ...p, coins_amount: e.target.value }))
                }
              />
            </div>

            <div>
              <div className="mb-1 text-[11px] font-semibold text-white/70">
                Method
              </div>
              <select
                className="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-[12px] text-white"
                value={form.method}
                onChange={(e) =>
                  setForm((p) => ({ ...p, method: e.target.value }))
                }
              >
                <option>GCash</option>
                <option>PayMaya</option>
                <option>Bank</option>
                <option>Cash</option>
              </select>
            </div>
          </div>

          {/* OPTIONALS */}
          <input
            className="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-[12px] text-white"
            placeholder="Reference (optional)"
            value={form.reference}
            onChange={(e) =>
              setForm((p) => ({ ...p, reference: e.target.value }))
            }
          />

          <input
            className="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-[12px] text-white"
            placeholder="Proof URL (optional)"
            value={form.proof_url}
            onChange={(e) =>
              setForm((p) => ({ ...p, proof_url: e.target.value }))
            }
          />

          <textarea
            rows={3}
            className="w-full resize-none rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-[12px] text-white"
            placeholder="Notes (optional)"
            value={form.notes}
            onChange={(e) =>
              setForm((p) => ({ ...p, notes: e.target.value }))
            }
          />
        </div>

        {/* FEEDBACK */}
        {err && (
          <div className="mt-3 rounded-xl border border-rose-500/20 bg-rose-500/10 px-3 py-2 text-[12px] text-rose-100">
            {err}
          </div>
        )}

        {okMsg && (
          <div className="mt-3 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-3 py-2 text-[12px] text-emerald-100">
            {okMsg}
          </div>
        )}

        {/* ACTIONS */}
        <div className="mt-4 flex justify-end gap-2">
          <button
            className="rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-[12px] text-white/80"
            onClick={onClose}
            disabled={loading}
          >
            Cancel
          </button>
          <button
            className="rounded-xl bg-emerald-500/90 px-4 py-2 text-[12px] font-semibold text-black disabled:opacity-50"
            onClick={submit}
            disabled={!canSubmit || loading}
          >
            {loading ? "Saving…" : "Create recharge"}
          </button>
        </div>
      </div>
    </div>
  );
}
