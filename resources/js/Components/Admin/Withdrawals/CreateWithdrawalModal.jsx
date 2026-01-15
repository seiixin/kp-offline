import React, { useEffect, useMemo, useState } from "react";
import axios from "axios";

export default function CreateWithdrawalModal({ onClose, onSaved }) {
  const [form, setForm] = useState({
    mongo_user_id: "",
    diamonds_amount: "",
    payout_cents: "",
    payout_method: "gcash",
    reference: "",
    notes: "",
  });

  /* ---------- dropdown state ---------- */
  const [userQuery, setUserQuery] = useState("");
  const [userOptions, setUserOptions] = useState([]);
  const [loadingUsers, setLoadingUsers] = useState(false);

  /* ---------- ui state ---------- */
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  function update(key, value) {
    setForm((prev) => ({ ...prev, [key]: value }));
  }

  /* =========================
     FETCH USERS (DROPDOWN)
  ========================= */
  useEffect(() => {
    const controller = new AbortController();
    setLoadingUsers(true);

    axios
      .get("/agent/users/dropdown", {
        params: { q: userQuery },
        signal: controller.signal,
        withCredentials: true,
      })
      .then((res) => setUserOptions(res.data?.data || []))
      .catch(() => {})
      .finally(() => setLoadingUsers(false));

    return () => controller.abort();
  }, [userQuery]);

  /* =========================
     VALIDATION
  ========================= */
  const canSubmit = useMemo(() => {
    return (
      String(form.mongo_user_id || "").length === 24 &&
      Number(form.diamonds_amount || 0) > 0 &&
      Number(form.payout_cents || 0) > 0
    );
  }, [form]);

  /* =========================
     SUBMIT
  ========================= */
  async function submit() {
    setError(null);

    if (!canSubmit) {
      setError("User, diamonds, and payout are required.");
      return;
    }

    setLoading(true);
    try {
      await axios.post(
        "/agent/withdrawals",
        {
          mongo_user_id: form.mongo_user_id,
          diamonds_amount: Number(form.diamonds_amount),
          payout_cents: Number(form.payout_cents),
          payout_method: form.payout_method,
          reference: form.reference || null,
          notes: form.notes || null,
        },
        { withCredentials: true }
      );

      onSaved?.();
    } catch (e) {
      setError(
        e.response?.data?.message ||
          "Failed to create withdrawal. Please try again."
      );
    } finally {
      setLoading(false);
    }
  }

  /* =========================
     UI
  ========================= */
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70">
      <div className="w-full max-w-md rounded-2xl border border-white/10 bg-[#0f0f0f] p-5">
        <div className="text-sm font-semibold text-white">
          New Withdrawal
        </div>

        <div className="mt-4 space-y-3">
          {/* USER DROPDOWN */}
          <div>
            <input
              className="mb-1 w-full rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-[12px] text-white"
              placeholder="Search name or username…"
              value={userQuery}
              onChange={(e) => setUserQuery(e.target.value)}
            />

            <select
              className="w-full rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-[12px] text-white"
              value={form.mongo_user_id}
              onChange={(e) => update("mongo_user_id", e.target.value)}
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

          <input
            type="number"
            className="w-full rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-[12px] text-white"
            placeholder="Diamonds"
            value={form.diamonds_amount}
            onChange={(e) => update("diamonds_amount", e.target.value)}
          />

          <input
            type="number"
            className="w-full rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-[12px] text-white"
            placeholder="Payout (cents)"
            value={form.payout_cents}
            onChange={(e) => update("payout_cents", e.target.value)}
          />

          <select
            className="w-full rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-[12px] text-white"
            value={form.payout_method}
            onChange={(e) => update("payout_method", e.target.value)}
          >
            <option value="gcash">GCash</option>
            <option value="bank">Bank</option>
          </select>

          <input
            className="w-full rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-[12px] text-white"
            placeholder="Reference (optional)"
            value={form.reference}
            onChange={(e) => update("reference", e.target.value)}
          />

          <textarea
            rows={3}
            className="w-full resize-none rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-[12px] text-white"
            placeholder="Notes (optional)"
            value={form.notes}
            onChange={(e) => update("notes", e.target.value)}
          />
        </div>

        {error && (
          <div className="mt-3 text-[12px] text-red-400">
            {error}
          </div>
        )}

        <div className="mt-4 flex justify-end gap-2">
          <button
            onClick={onClose}
            className="rounded-lg border border-white/10 px-3 py-1.5 text-[12px] text-white/70"
            disabled={loading}
          >
            Cancel
          </button>

          <button
            onClick={submit}
            disabled={!canSubmit || loading}
            className="rounded-lg bg-emerald-500 px-3 py-1.5 text-[12px] font-semibold text-black disabled:opacity-60"
          >
            {loading ? "Saving…" : "Create"}
          </button>
        </div>
      </div>
    </div>
  );
}
