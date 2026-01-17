import React, { useEffect, useMemo, useState } from "react";
import axios from "axios";

/**
 * Business constants
 * 11,200 diamonds = $1.00
 * UI preview only — backend is authoritative
 */
const DIAMONDS_PER_USD = 11200;
const USD_TO_PHP = 56; // UI preview rate only

export default function CreateWithdrawalModal({ onClose, onSaved }) {
  /* =========================
     FORM STATE
  ========================= */
  const [form, setForm] = useState({
    mongo_user_id: "",
    diamonds_amount: "",
  });

  function update(key, value) {
    setForm((prev) => ({ ...prev, [key]: value }));
  }

  /* =========================
     USERS DROPDOWN
  ========================= */
  const [userQuery, setUserQuery] = useState("");
  const [userOptions, setUserOptions] = useState([]);
  const [loadingUsers, setLoadingUsers] = useState(false);

  /* =========================
     AGENT CASH WALLET
  ========================= */
  const [cashWallet, setCashWallet] = useState(null);
  const [loadingWallet, setLoadingWallet] = useState(false);

  /* =========================
     UI STATE
  ========================= */
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  /* =========================
     FETCH AGENT CASH WALLET
  ========================= */
  useEffect(() => {
    let mounted = true;
    setLoadingWallet(true);

    axios
      .get("/agent/wallets", { withCredentials: true })
      .then((res) => {
        const raw = Array.isArray(res.data)
          ? res.data
          : res.data?.data || [];

        const phpWallet = raw.find(
          (w) => String(w.asset).toUpperCase() === "PHP"
        );

        if (mounted) setCashWallet(phpWallet || null);
      })
      .catch(() => mounted && setCashWallet(null))
      .finally(() => mounted && setLoadingWallet(false));

    return () => {
      mounted = false;
    };
  }, []);

  /* =========================
     FETCH USERS
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
     DERIVED VALUES
  ========================= */
  const diamonds = Number(form.diamonds_amount || 0);

  const payoutUsd = useMemo(() => {
    if (!diamonds || diamonds < DIAMONDS_PER_USD) return 0;
    return diamonds / DIAMONDS_PER_USD;
  }, [diamonds]);

  const payoutPhp = useMemo(() => {
    return payoutUsd * USD_TO_PHP;
  }, [payoutUsd]);

  /* =========================
     VALIDATION
  ========================= */
  const canSubmit = useMemo(() => {
    if (!cashWallet) return false;

    return (
      String(form.mongo_user_id).length === 24 &&
      diamonds >= 112000 &&
      payoutPhp * 100 <= Number(cashWallet.available_cents)
    );
  }, [form.mongo_user_id, diamonds, payoutPhp, cashWallet]);

  /* =========================
     SUBMIT
  ========================= */
  async function submit() {
    setError(null);

    if (!canSubmit) {
      setError("Please complete all required fields correctly.");
      return;
    }

    setLoading(true);
    try {
      await axios.post(
        "/agent/withdrawals",
        {
          mongo_user_id: form.mongo_user_id,
          diamonds_amount: diamonds,
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
          New Player Withdrawal
        </div>

        <div className="mt-4 space-y-3">
          {/* CASH WALLET (READ-ONLY) */}
          <div className="rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-[12px] text-white">
            {loadingWallet
              ? "Loading cash wallet…"
              : cashWallet
              ? `CASH (PHP) — ₱${(
                  Number(cashWallet.available_cents) / 100
                ).toLocaleString()}`
              : "No cash wallet available"}
          </div>

          {/* USER SEARCH */}
          <input
            className="w-full rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-[12px] text-white"
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

          {/* DIAMONDS */}
          <input
            type="number"
            min={112000}
            className="w-full rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-[12px] text-white"
            placeholder="Diamonds (min 112,000)"
            value={form.diamonds_amount}
            onChange={(e) => update("diamonds_amount", e.target.value)}
          />

          {/* PAYOUT PREVIEW */}
          <div className="rounded-lg border border-white/10 bg-black/30 px-3 py-2 text-[12px] text-white">
            Estimated payout:{" "}
            <span className="font-semibold text-emerald-400">
              ₱{payoutPhp.toFixed(2)}
            </span>
          </div>

          {cashWallet && (
            <div className="text-[11px] text-white/60">
              Available cash: ₱
              {(Number(cashWallet.available_cents) / 100).toLocaleString()}
            </div>
          )}
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
