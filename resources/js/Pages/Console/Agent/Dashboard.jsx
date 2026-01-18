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

function StatCard({ label, value, accent = "emerald" }) {
  const color =
    accent === "sky"
      ? "text-sky-300"
      : accent === "violet"
      ? "text-violet-300"
      : "text-emerald-300";

  return (
    <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
      <div className="text-[10px] font-semibold tracking-[0.28em] text-white/40">
        {label}
      </div>
      <div className={`mt-3 text-3xl font-semibold ${color}`}>
        {value}
      </div>
    </div>
  );
}

/* ================= PAGE ================= */

export default function AgentDashboard({ active }) {
  const [wallets, setWallets] = useState(null);
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

      const res = await axios.get("/agent/wallet/overview", {
        withCredentials: true,
      });

      setWallets(res.data?.data || null);
    } catch {
      setWallets(null);
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
    <AdminLayout title="Agent workspace" active={active}>
      {/* ================= WALLET OVERVIEW ================= */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-4">
        <StatCard
          label="OPERATIONAL FUNDS (CASH · PHP)"
          value={
            loading ? "—" : formatPhp(cash?.available_cents)
          }
          accent="emerald"
        />

        <StatCard
          label="CASH RESERVED"
          value={
            loading ? "—" : formatPhp(cash?.reserved_cents)
          }
          accent="emerald"
        />

        <StatCard
          label="DIAMONDS EARNINGS"
          value={
            loading ? "—" : formatDiamonds(diamonds?.available)
          }
          accent="sky"
        />

        <StatCard
          label="DIAMONDS RESERVED"
          value={
            loading ? "—" : formatDiamonds(diamonds?.reserved)
          }
          accent="sky"
        />
      </div>

      {/* ================= QUICK ACCESS ================= */}
      <div className="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-4">
        <a
          href={route("console.agent.recharges")}
          className="rounded-2xl border border-white/10 bg-white/[0.03] p-5 hover:bg-white/5"
        >
          <div className="text-[12px] font-semibold">Recharges</div>
          <div className="mt-1 text-[11px] text-white/55">
            Manual offline coin top-ups for players.
          </div>
        </a>

        <a
          href={route("console.agent.withdrawals")}
          className="rounded-2xl border border-white/10 bg-white/[0.03] p-5 hover:bg-white/5"
        >
          <div className="text-[12px] font-semibold">Withdrawals</div>
          <div className="mt-1 text-[11px] text-white/55">
            Process player withdrawal requests.
          </div>
        </a>

        <a
          href={route("console.agent.wallet")}
          className="rounded-2xl border border-white/10 bg-white/[0.03] p-5 hover:bg-white/5"
        >
          <div className="text-[12px] font-semibold">Wallet</div>
          <div className="mt-1 text-[11px] text-white/55">
            View operational funds and ledger activity.
          </div>
        </a>

        <a
          href={route("console.agent.commissions")}
          className="rounded-2xl border border-white/10 bg-white/[0.03] p-5 hover:bg-white/5"
        >
          <div className="text-[12px] font-semibold">Commissions</div>
          <div className="mt-1 text-[11px] text-white/55">
            Track diamond earnings and payouts.
          </div>
        </a>
      </div>
    </AdminLayout>
  );
}
