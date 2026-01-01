import React from "react";
import { Link } from "@inertiajs/react";
import AdminLayout from "@/Layouts/AdminLayout";

function fmtMoney(value, currency = "PHP") {
  const n = Number(value || 0);
  try {
    return new Intl.NumberFormat("en-PH", {
      style: "currency",
      currency,
      maximumFractionDigits: 2,
    }).format(n);
  } catch {
    // fallback if currency code is unknown
    return `₱${n.toFixed(2)}`;
  }
}

function StatCard({ label, value }) {
  return (
    <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-5 shadow-[0_0_0_1px_rgba(255,255,255,0.02)]">
      <div className="text-[10px] font-semibold tracking-[0.28em] text-white/40">
        {label}
      </div>
      <div className="mt-3 text-3xl font-semibold text-emerald-200">{value}</div>
    </div>
  );
}

function Shortcut({ title, desc, href }) {
  return (
    <Link
      href={href}
      className="rounded-2xl border border-white/10 bg-white/[0.03] p-5 shadow-[0_0_0_1px_rgba(255,255,255,0.02)] transition hover:bg-white/5"
    >
      <div className="text-[12px] font-semibold text-white/90">{title}</div>
      <div className="mt-1 text-[11px] text-white/55">{desc}</div>
    </Link>
  );
}

export default function Dashboard({ active, wallet, today }) {
  const currency = wallet?.currency || "PHP";
  const available = wallet?.available ?? 0;
  const reserved = wallet?.reserved ?? 0;

  const rechargesToday = today?.recharges ?? 0;
  const withdrawalsToday = today?.withdrawals ?? 0;

  return (
    <AdminLayout title="Agent workspace" active={active}>
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-4">
        <StatCard label="AVAILABLE WALLET BALANCE" value={fmtMoney(available, currency)} />
        <StatCard label="RESERVED FOR PAYOUTS" value={fmtMoney(reserved, currency)} />
        <StatCard label="RECHARGES TODAY" value={Number(rechargesToday)} />
        <StatCard label="WITHDRAWALS TODAY" value={Number(withdrawalsToday)} />
      </div>

      <div className="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-4">
        <Shortcut title="Recharges" desc="Manual offline coin top-ups for players." href={route("console.agent.recharges")} />
        <Shortcut title="Withdrawals" desc="Process and monitor player withdrawal tickets." href={route("console.agent.withdrawals")} />
        <Shortcut title="Wallet" desc="View internal wallet and credit movement." href={route("console.agent.wallet")} />
        <Shortcut title="Commissions" desc="Track earnings from completed recharges." href={route("console.agent.commissions")} />
      </div>
    </AdminLayout>
  );
}
