import React, { useEffect, useMemo, useState } from "react";
import axios from "axios";

/**
 * UI PREVIEW CONSTANTS
 * Backend is authoritative
 */
const COINS_PER_USD = 14000; // 14,000 coins = $1
const USD_TO_PHP = 56;

export default function NewOfflineRechargeModal({
  open,
  onClose,
  onCreated,
}) {
  /* =========================
     FORM STATE
  ========================= */
  const [form, setForm] = useState({
    mongo_user_id: "",
    coins_amount: "",
    method: "GCash",
    reference: "",
    proof_url: "",
  });

  const update = (k, v) => setForm((p) => ({ ...p, [k]: v }));

  /* =========================
     USERS DROPDOWN
  ========================= */
  const [userQuery, setUserQuery] = useState("");
  const [userOptions, setUserOptions] = useState([]);
  const [loadingUsers, setLoadingUsers] = useState(false);

  /* =========================
     AGENT CASH WALLET (PHP)
  ========================= */
  const [cashWallet, setCashWallet] = useState(null);
  const [loadingWallet, setLoadingWallet] = useState(false);

  /* =========================
     UI STATE
  ========================= */
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  /* =========================
     RESET ON OPEN
  ========================= */
  useEffect(() => {
    if (!open) return;

    setError(null);
    setForm({
      mongo_user_id: "",
      coins_amount: "",
      method: "GCash",
      reference: "",
      proof_url: "",
    });
  }, [open]);

  /* =========================
     FETCH AGENT CASH WALLET
  ========================= */
  useEffect(() => {
    if (!open) return;

    let alive = true;
    setLoadingWallet(true);

    axios
      .get("/agent/wallets", { withCredentials: true })
      .then((res) => {
        const list = Array.isArray(res.data)
          ? res.data
          : res.data?.data || [];

        const phpWallet = list.find(
          (w) => String(w.asset).toUpperCase() === "PHP"
        );

        if (alive) setCashWallet(phpWallet || null);
      })
      .catch(() => alive && setCashWallet(null))
      .finally(() => alive && setLoadingWallet(false));

    return () => {
      alive = false;
    };
  }, [open]);

  /* =========================
     FETCH USERS
  ========================= */
  useEffect(() => {
    if (!open) return;

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
  }, [userQuery, open]);

  /* =========================
     DERIVED VALUES (UI ONLY)
  ========================= */
  const coins = Number(form.coins_amount || 0);

  const usdValue = useMemo(() => {
    if (coins <= 0) return 0;
    return coins / COINS_PER_USD;
  }, [coins]);

  const amountUsdCents = useMemo(
    () => Math.max(0, Math.round(usdValue * 100)),
    [usdValue]
  );

  const phpValue = useMemo(() => usdValue * USD_TO_PHP, [usdValue]);

  const hasEnoughCash = useMemo(() => {
    if (!cashWallet) return false;
    return phpValue * 100 <= Number(cashWallet.available_cents);
  }, [phpValue, cashWallet]);

  /* =========================
     VALIDATION
  ========================= */
  const canSubmit =
    String(form.mongo_user_id).length === 24 &&
    coins > 0 &&
    amountUsdCents > 0 &&
    hasEnoughCash &&
    !!form.method;

  /* =========================
     SUBMIT
  ========================= */
  async function submit() {
    if (!canSubmit || loading) return;

    setError(null);
    setLoading(true);

    try {
      await axios.post(
        "/agent/recharges",
        {
          mongo_user_id: form.mongo_user_id,
          coins_amount: coins,
          amount_usd_cents: amountUsdCents, // REQUIRED
          method: form.method,
          reference: form.reference || null,
          proof_url: form.proof_url || null,
        },
        { withCredentials: true }
      );

      onCreated?.(); // auto-close handled by parent
    } catch (e) {
      setError(
        e.response?.data?.message ||
          "Failed to create offline recharge."
      );
    } finally {
      setLoading(false);
    }
  }

  /* =========================
     RENDER GUARD
  ========================= */
  if (!open) return null;

  /* =========================
     UI
  ========================= */
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70">
      <div className="w-full max-w-md rounded-2xl border border-white/10 bg-[#0f0f0f] p-5">
        <div className="flex justify-between items-center">
          <div className="text-sm font-semibold text-white">
            New Offline Recharge
          </div>
          <button
            onClick={onClose}
            disabled={loading}
            className="text-white/60"
          >
            ✕
          </button>
        </div>

        <div className="mt-4 space-y-3">
          {/* CASH WALLET */}
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
            placeholder="Search user…"
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

          {/* COINS */}
          <input
            type="number"
            min={1}
            className="w-full rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-[12px] text-white"
            placeholder="Coins amount"
            value={form.coins_amount}
            onChange={(e) => update("coins_amount", e.target.value)}
          />

          {/* METHOD */}
          <select
            className="w-full rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-[12px] text-white"
            value={form.method}
            onChange={(e) => update("method", e.target.value)}
          >
            <option>GCash</option>
            <option>PayMaya</option>
            <option>Bank</option>
            <option>Cash</option>
          </select>

          {/* USD + PHP PREVIEW */}
          <div className="rounded-lg border border-white/10 bg-black/30 px-3 py-2 text-[12px] text-white space-y-1">
            <div>
              USD value:&nbsp;
              <span className="font-semibold text-sky-400">
                ${usdValue.toFixed(2)}
              </span>
            </div>
            <div>
              PHP deduction:&nbsp;
              <span
                className={`font-semibold ${
                  hasEnoughCash ? "text-emerald-400" : "text-red-400"
                }`}
              >
                ₱{phpValue.toFixed(2)}
              </span>
            </div>
          </div>

          {!hasEnoughCash && (
            <div className="text-[11px] text-red-400">
              Insufficient agent cash balance.
            </div>
          )}

          {/* OPTIONALS */}
          <input
            className="w-full rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-[12px] text-white"
            placeholder="Reference (optional)"
            value={form.reference}
            onChange={(e) => update("reference", e.target.value)}
          />

          <input
            className="w-full rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-[12px] text-white"
            placeholder="Proof URL (optional)"
            value={form.proof_url}
            onChange={(e) => update("proof_url", e.target.value)}
          />
        </div>

        {error && (
          <div className="mt-3 text-[12px] text-red-400">{error}</div>
        )}

        <div className="mt-4 flex justify-end gap-2">
          <button
            onClick={onClose}
            disabled={loading}
            className="rounded-lg border border-white/10 px-3 py-1.5 text-[12px] text-white/70"
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
