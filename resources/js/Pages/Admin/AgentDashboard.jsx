import React from "react";
import AdminLayout from "@/Layouts/AdminLayout";

function Card({ title, desc, right }) {
  return (
    <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-5 shadow-[0_0_0_1px_rgba(255,255,255,0.02)]">
      <div className="flex items-start justify-between gap-4">
        <div>
          <div className="text-[13px] font-semibold text-white/90">{title}</div>
          {desc ? <div className="mt-1 text-[12px] text-white/55">{desc}</div> : null}
        </div>
        {right}
      </div>
    </div>
  );
}

export default function AgentDashboard({ active }) {
  return (
    <AdminLayout title="Agent workspace" active={active}>
      
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-4">
        <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
          <div className="text-[10px] font-semibold tracking-[0.28em] text-white/40">AVAILABLE WALLET BALANCE</div>
          <div className="mt-3 text-3xl font-semibold text-emerald-200">₱0.00</div>
        </div>
        <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
          <div className="text-[10px] font-semibold tracking-[0.28em] text-white/40">RESERVED FOR PAYOUTS</div>
          <div className="mt-3 text-3xl font-semibold text-emerald-200">₱0.00</div>
        </div>
        <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
          <div className="text-[10px] font-semibold tracking-[0.28em] text-white/40">RECHARGES TODAY</div>
          <div className="mt-3 text-3xl font-semibold text-emerald-200">0</div>
        </div>
        <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
          <div className="text-[10px] font-semibold tracking-[0.28em] text-white/40">WITHDRAWALS TODAY</div>
          <div className="mt-3 text-3xl font-semibold text-emerald-200">0</div>
        </div>
      </div>

      <div className="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-4">
        <a href={route("console.agent.recharges")} className="rounded-2xl border border-white/10 bg-white/[0.03] p-5 hover:bg-white/5">
          <div className="text-[12px] font-semibold">Recharges</div>
          <div className="mt-1 text-[11px] text-white/55">Manual offline coin top-ups for players.</div>
        </a>
        <a href={route("console.agent.withdrawals")} className="rounded-2xl border border-white/10 bg-white/[0.03] p-5 hover:bg-white/5">
          <div className="text-[12px] font-semibold">Withdrawals</div>
          <div className="mt-1 text-[11px] text-white/55">Process and monitor player withdrawal tickets.</div>
        </a>
        <a href={route("console.agent.wallet")} className="rounded-2xl border border-white/10 bg-white/[0.03] p-5 hover:bg-white/5">
          <div className="text-[12px] font-semibold">Wallet</div>
          <div className="mt-1 text-[11px] text-white/55">View internal wallet and credit movement.</div>
        </a>
        <a href={route("console.agent.commissions")} className="rounded-2xl border border-white/10 bg-white/[0.03] p-5 hover:bg-white/5">
          <div className="text-[12px] font-semibold">Commissions</div>
          <div className="mt-1 text-[11px] text-white/55">Track earnings from completed recharges.</div>
        </a>
      </div>

    </AdminLayout>
  );
}
