import React, { useEffect, useState } from "react";
import AdminLayout from "@/Layouts/AdminLayout";
import axios from "axios";

/* ================= HELPERS ================= */

function formatPhp(cents) {
  return `₱${(Number(cents || 0) / 100).toFixed(2)}`;
}

function formatDiamonds(amount) {
  return Number(amount || 0).toLocaleString();
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
  const [cashLedger, setCashLedger] = useState([]);
  const [loading, setLoading] = useState(true);

  async function load() {
    setLoading(true);

    try {
      // ensure diamonds wallet exists (idempotent)
      await axios.post(
        "/agent/wallets/ensure-diamonds",
        {},
        { withCredentials: true }
      );

      const [overviewRes, ledgerRes] = await Promise.all([
        axios.get("/agent/wallet/overview", { withCredentials: true }),
        axios.get("/agent/wallet/cash-ledger", { withCredentials: true }),
      ]);

      setWallets(overviewRes.data?.data || null);
      setCashLedger(ledgerRes.data?.data || []);
    } catch {
      setWallets(null);
      setCashLedger([]);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    load();
  }, []);

  const cash = wallets?.cash;
  const diamonds = wallets?.diamonds;

  return (
    <AdminLayout title="Agent Wallets" active={active}>
      {/* ================= WALLET CARDS ================= */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {/* CASH WALLET */}
        <Card
          title="CASH WALLET (PHP)"
          subtitle="Operational funds · Recharge & Withdrawals"
        >
          <div className="text-3xl font-semibold text-emerald-300">
            {loading ? "—" : formatPhp(cash?.available_cents)}
          </div>
          <div className="mt-1 text-[11px] text-white/50">
            Reserved: {loading ? "—" : formatPhp(cash?.reserved_cents)}
          </div>
        </Card>

        {/* DIAMONDS WALLET */}
        <Card
          title="DIAMONDS WALLET"
          subtitle="Commission · Game economy"
        >
          <div className="text-3xl font-semibold text-sky-300">
            {loading ? "—" : formatDiamonds(diamonds?.available)}
          </div>
          <div className="mt-1 text-[11px] text-white/50">
            Reserved:{" "}
            {loading ? "—" : formatDiamonds(diamonds?.reserved)}
          </div>
        </Card>

        {/* ACTIONS */}
        <Card title="ACTIONS">
          <div className="flex gap-2">
            <button
              disabled
              className="rounded-xl bg-white/10 px-4 py-2 text-[12px] text-white/70"
            >
              Add cash
            </button>
            <button
              disabled
              className="rounded-xl bg-white/10 px-4 py-2 text-[12px] text-white/70"
            >
              Request payout
            </button>
          </div>
        </Card>
      </div>

      {/* ================= CASH LEDGER ================= */}
      <div className="mt-6 overflow-hidden rounded-2xl border border-white/10 bg-white/[0.03]">
        <div className="px-4 py-3 text-[13px] font-semibold text-white/85">
          Cash Ledger
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
              <th className="px-4 py-3 text-[10px] tracking-[0.20em]">
                NOTE
              </th>
            </tr>
          </thead>

          <tbody className="divide-y divide-white/10">
            {loading && (
              <tr>
                <td colSpan={4} className="px-4 py-6 text-white/40">
                  Loading…
                </td>
              </tr>
            )}

            {!loading && cashLedger.length === 0 && (
              <tr>
                <td colSpan={4} className="px-4 py-6 text-white/40">
                  No cash ledger entries
                </td>
              </tr>
            )}

            {cashLedger.map((row) => (
              <tr key={row.id} className="bg-black/20">
                <td className="px-4 py-3 text-white/85">
                  {row.event_type}
                </td>
                <td className="px-4 py-3 text-white/85">
                  {row.direction === "credit" ? "+" : "−"}
                </td>
                <td className="px-4 py-3 text-white/85">
                  {formatPhp(row.amount_cents)}
                </td>
                <td className="px-4 py-3 text-white/60">
                  {row.meta?.note || "—"}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </AdminLayout>
  );
}
