import React, { useMemo, useState } from "react";

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
  // Laravel sets XSRF-TOKEN cookie (URL-encoded)
  const raw = getCookie("XSRF-TOKEN");
  return raw ? decodeURIComponent(raw) : "";
}

export default function NewRechargeModal({ open, onClose, onCreated }) {
  const [form, setForm] = useState({
    mongo_user_id: "",
    coins_amount: "",
    method: "GCash",
    reference: "",
    proof_url: "",
    notes: "",
  });

  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState("");
  const [okMsg, setOkMsg] = useState("");

  const canSubmit = useMemo(() => {
    return (
      String(form.mongo_user_id || "").trim().length === 24 &&
      Number(form.coins_amount || 0) > 0 &&
      String(form.method || "").trim().length > 0
    );
  }, [form]);

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
          mongo_user_id: String(form.mongo_user_id || "").trim(),
          reference: form.reference || null,
          proof_url: form.proof_url || null,
          notes: form.notes || null,
        }),
      });

      // 419 usually returns HTML; try JSON first, fallback to text
      const contentType = res.headers.get("content-type") || "";
      const data = contentType.includes("application/json") ? await res.json() : { error: await res.text() };

      if (!res.ok) {
        const msg =
          data?.error ||
          (typeof data === "string" ? data : "") ||
          "Failed to create recharge.";
        throw new Error(
          res.status === 419
            ? "CSRF token mismatch (419). Add <meta name=\"csrf-token\" ...> in your Blade layout used by Inertia."
            : msg
        );
      }

      if (data?.already_processed) {
        setOkMsg("Already processed (idempotency).");
      } else {
        setOkMsg("Recharge created successfully.");
      }

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
      setErr(e.message || "Failed to create recharge.");
    } finally {
      setLoading(false);
    }
  };

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-3 py-6">
      <div className="w-full max-w-lg rounded-2xl border border-white/10 bg-[#0b0f1a] p-4 shadow-2xl">
        <div className="flex items-start justify-between gap-3">
          <div>
            <div className="text-[13px] font-semibold text-white/90">New offline recharge</div>

          </div>
          <button
            className="rounded-lg border border-white/10 bg-white/5 px-2 py-1 text-[12px] text-white/70 hover:bg-white/10"
            onClick={onClose}
            disabled={loading}
          >
            Close
          </button>
        </div>

        <div className="mt-4 grid grid-cols-1 gap-3">
          <div>
            <div className="mb-1 text-[11px] font-semibold text-white/70">Mongo User ID</div>
            <input
              className="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-[12px] text-white placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-white/10"
              placeholder="24-char ObjectId"
              value={form.mongo_user_id}
              onChange={(e) => setForm((p) => ({ ...p, mongo_user_id: e.target.value }))}
            />
          </div>

          <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
              <div className="mb-1 text-[11px] font-semibold text-white/70">Coins amount</div>
              <input
                type="number"
                min="1"
                className="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-[12px] text-white placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-white/10"
                placeholder="e.g. 1000"
                value={form.coins_amount}
                onChange={(e) => setForm((p) => ({ ...p, coins_amount: e.target.value }))}
              />
            </div>
            <div>
              <div className="mb-1 text-[11px] font-semibold text-white/70">Method</div>
              <select
                className="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-[12px] text-white focus:outline-none focus:ring-2 focus:ring-white/10"
                value={form.method}
                onChange={(e) => setForm((p) => ({ ...p, method: e.target.value }))}
              >
                <option>GCash</option>
                <option>PayMaya</option>
                <option>Bank</option>
                <option>Cash</option>
              </select>
            </div>
          </div>

          <div>
            <div className="mb-1 text-[11px] font-semibold text-white/70">Reference (optional)</div>
            <input
              className="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-[12px] text-white placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-white/10"
              placeholder="Receipt / reference number"
              value={form.reference}
              onChange={(e) => setForm((p) => ({ ...p, reference: e.target.value }))}
            />
          </div>

          <div>
            <div className="mb-1 text-[11px] font-semibold text-white/70">Proof URL (optional)</div>
            <input
              className="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-[12px] text-white placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-white/10"
              placeholder="https://..."
              value={form.proof_url}
              onChange={(e) => setForm((p) => ({ ...p, proof_url: e.target.value }))}
            />
          </div>

          <div>
            <div className="mb-1 text-[11px] font-semibold text-white/70">Notes (optional)</div>
            <textarea
              rows={3}
              className="w-full resize-none rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-[12px] text-white placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-white/10"
              placeholder="Any notes for audit"
              value={form.notes}
              onChange={(e) => setForm((p) => ({ ...p, notes: e.target.value }))}
            />
          </div>
        </div>

        {err ? (
          <div className="mt-3 rounded-xl border border-rose-500/20 bg-rose-500/10 px-3 py-2 text-[12px] text-rose-100">
            {err}
          </div>
        ) : null}

        {okMsg ? (
          <div className="mt-3 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-3 py-2 text-[12px] text-emerald-100">
            {okMsg}
          </div>
        ) : null}

        <div className="mt-4 flex items-center justify-end gap-2">
          <button
            className="rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-[12px] text-white/80 hover:bg-white/10 disabled:opacity-50"
            onClick={onClose}
            disabled={loading}
          >
            Cancel
          </button>
          <button
            className="rounded-xl bg-emerald-500/90 px-4 py-2 text-[12px] font-semibold text-black hover:bg-emerald-400 disabled:opacity-50"
            onClick={submit}
            disabled={!canSubmit || loading}
          >
            {loading ? "Saving..." : "Create recharge"}
          </button>
        </div>
      </div>
    </div>
  );
}
