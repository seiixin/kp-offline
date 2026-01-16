import React, { useEffect, useMemo, useState } from "react";
import axios from "axios";

/**
 * Business constants
 * 11,200 diamonds = $1.00
 */
const DIAMONDS_PER_USD = 11200;

export default function CreateWithdrawalModal({ onClose, onSaved }) {
  /* =========================
     FORM STATE
  ========================= */
  const [form, setForm] = useState({
    wallet_id: "",
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
     WALLETS DROPDOWN
  ========================= */
  const [wallets, setWallets] = useState([]);
  const [loadingWallets, setLoadingWallets] = useState(false);

  /* =========================
     UI STATE
  ========================= */
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  /* =========================
     FETCH AGENT WALLETS
  ========================= */
  useEffect(() => {
    let mounted = true;
    setLoadingWallets(true);

    axios
      .get("/agent/wallets", { withCredentials: true })
      .then((res) => {
        const raw = Array.isArray(res.data)
          ? res.data
          : res.data?.data || [];

        const diamondsWallets = raw.filter(
          (w) => String(w.asset).toUpperCase() === "DIAMONDS"
        );

        if (!mounted) return;

        setWallets(diamondsWallets);

        if (diamondsWallets.length === 1) {
          setForm((f) => ({
            ...f,
            wallet_id: String(diamondsWallets[0].id),
          }));
        }
      })
      .catch(() => mounted && setWallets([]))
      .finally(() => mounted && setLoadingWallets(false));

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
  const selectedWallet = useMemo(
    () => wallets.find((w) => String(w.id) === String(form.wallet_id)),
    [wallets, form.wallet_id]
  );

  const diamonds = Number(form.diamonds_amount || 0);

  const payoutUsd = useMemo(() => {
    if (!diamonds || diamonds < DIAMONDS_PER_USD) return 0;
    return diamonds / DIAMONDS_PER_USD;
  }, [diamonds]);

  const payoutUsdFormatted = useMemo(
    () =>
      payoutUsd > 0
        ? `$${payoutUsd.toFixed(2)}`
        : "$0.00",
    [payoutUsd]
  );

  /* =========================
     VALIDATION
  ========================= */
  const canSubmit = useMemo(() => {
    return (
      Number(form.wallet_id) > 0 &&
      String(form.mongo_user_id).length === 24 &&
      diamonds >= 112000 &&
      selectedWallet &&
      diamonds <= Number(selectedWallet.available_cents)
    );
  }, [form, diamonds, selectedWallet]);

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
          wallet_id: Number(form.wallet_id),
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
          New Withdrawal
        </div>

        <div className="mt-4 space-y-3">
          {/* WALLET */}
          <select
            className="w-full rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-[12px] text-white"
            value={form.wallet_id}
            onChange={(e) => update("wallet_id", e.target.value)}
          >
            <option value="">
              {loadingWallets ? "Loading wallets…" : "Select wallet"}
            </option>
            {wallets.map((w) => (
              <option key={w.id} value={w.id}>
                DIAMONDS — {Number(w.available_cents).toLocaleString()}
              </option>
            ))}
          </select>

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
              {payoutUsdFormatted}
            </span>
          </div>

          {selectedWallet && (
            <div className="text-[11px] text-white/60">
              Available:{" "}
              {Number(selectedWallet.available_cents).toLocaleString()} diamonds
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
