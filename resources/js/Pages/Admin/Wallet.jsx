import React, { useEffect, useState } from "react";
import AdminLayout from "@/Layouts/AdminLayout";
import axios from "axios";

/* ================= HELPERS ================= */

function formatNumber(n) {
  return Number(n || 0).toLocaleString();
}

function renderDirection(direction) {
  return direction === "credit" ? (
    <span className="rounded-md bg-emerald-500/15 px-2 py-0.5 text-[11px] font-semibold text-emerald-400">
      CREDIT
    </span>
  ) : (
    <span className="rounded-md bg-rose-500/15 px-2 py-0.5 text-[11px] font-semibold text-rose-400">
      DEBIT
    </span>
  );
}

/* ================= CARD ================= */

function Card({ title, subtitle, children }) {
  return (
    <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
      <div className="text-[13px] font-semibold text-white/90">
        {title}
      </div>
      {subtitle && (
        <div className="mt-1 text-[11px] text-white/50">
          {subtitle}
        </div>
      )}
      <div className="mt-3">{children}</div>
    </div>
  );
}

/* ================= PAGE ================= */

export default function Wallet({ active }) {
  const [wallets, setWallets] = useState(null);
  const [ledger, setLedger] = useState([]);
  const [loading, setLoading] = useState(true);

  async function load() {
    setLoading(true);

    try {
      const [walletRes, ledgerRes] = await Promise.all([
        axios.get("/agent/wallet/overview", { withCredentials: true }),
        axios.get("/agent/wallet/coins-ledger", { withCredentials: true }),
      ]);

      setWallets(walletRes.data?.data || null);
      setLedger(ledgerRes.data?.data || []);
    } catch {
      setWallets(null);
      setLedger([]);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    load();
  }, []);

  const coins = wallets?.coins;
  const diamonds = wallets?.diamonds;

  return (
    <AdminLayout title="Agent Wallets" active={active}>
      {/* ================= WALLET CARDS ================= */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {/* COINS */}
        <Card
          title="COINS WALLET"
          subtitle="Recharge balance · Used to top up players"
        >
          <div className="text-3xl font-semibold text-yellow-300">
            {loading ? "—" : formatNumber(coins?.balance)}
          </div>
          <div className="mt-1 text-[11px] text-white/50">
            Available coins
          </div>
        </Card>

        {/* DIAMONDS */}
        <Card
          title="DIAMONDS WALLET"
          subtitle="Commission · Game economy"
        >
          <div className="text-3xl font-semibold text-sky-300">
            {loading ? "—" : formatNumber(diamonds?.balance)}
          </div>
          <div className="mt-1 text-[11px] text-white/50">
            Available diamonds
          </div>
        </Card>
      </div>

      {/* ================= COINS LEDGER ================= */}
      <div className="mt-6 overflow-hidden rounded-2xl border border-white/10 bg-white/[0.03]">
        <div className="px-4 py-3 text-[13px] font-semibold text-white/85">
          Coins Ledger
        </div>

        <table className="min-w-full text-left text-[12px]">
          <thead className="bg-white/5 text-white/45">
            <tr>
              <th className="px-4 py-3 text-[10px] tracking-[0.20em]">
                TYPE
              </th>
              <th className="px-4 py-3 text-[10px] tracking-[0.20em]">
                DIRECTION
              </th>
              <th className="px-4 py-3 text-[10px] tracking-[0.20em]">
                AMOUNT
              </th>
            </tr>
          </thead>

          <tbody className="divide-y divide-white/10">
            {loading && (
              <tr>
                <td colSpan={3} className="px-4 py-6 text-white/40">
                  Loading…
                </td>
              </tr>
            )}

            {!loading && ledger.length === 0 && (
              <tr>
                <td colSpan={3} className="px-4 py-6 text-white/40">
                  No ledger entries
                </td>
              </tr>
            )}

            {ledger.map((row) => (
              <tr key={row.id} className="bg-black/20">
                <td className="px-4 py-3 text-white/85">
                  {row.event_type}
                </td>
                <td className="px-4 py-3">
                  {renderDirection(row.direction)}
                </td>
                <td className="px-4 py-3 font-semibold text-yellow-300">
                  {formatNumber(row.amount_cents)} Coins
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* ================= FALLBACK ================= */}
      {!loading && !wallets && (
        <div className="mt-6 rounded-xl border border-white/10 bg-white/[0.03] px-4 py-3 text-[12px] text-white/60">
          Wallet data unavailable.
        </div>
      )}
    </AdminLayout>
  );
}
