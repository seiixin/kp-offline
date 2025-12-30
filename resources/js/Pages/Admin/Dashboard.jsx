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

export default function Dashboard({ active }) {
  return (
    <AdminLayout title="Welcome back, KittyParty operator" active={active}>
      
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-4">
        <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
          <div className="text-[10px] font-semibold tracking-[0.28em] text-white/40">COINS RECHARGED TODAY</div>
          <div className="mt-3 text-3xl font-semibold text-amber-200">0</div>
        </div>
        <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
          <div className="text-[10px] font-semibold tracking-[0.28em] text-white/40">PAYOUTS PROCESSED</div>
          <div className="mt-3 text-3xl font-semibold text-amber-200">0</div>
        </div>
        <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
          <div className="text-[10px] font-semibold tracking-[0.28em] text-white/40">ACTIVE OFFLINE AGENTS</div>
          <div className="mt-3 text-3xl font-semibold text-amber-200">0</div>
        </div>
        <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
          <div className="text-[10px] font-semibold tracking-[0.28em] text-white/40">FLAGGED TRANSACTIONS</div>
          <div className="mt-3 text-3xl font-semibold text-amber-200">0</div>
        </div>
      </div>

      <div className="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-5 lg:col-span-2">
          <div className="text-[13px] font-semibold text-white/90">Navigation</div>
          <div className="mt-1 text-[12px] text-white/55">
            Click through the wireframe sections below. All data is static for now.
          </div>

          <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
            <a href={route("console.agent.dashboard")} className="rounded-2xl border border-white/10 bg-black/20 p-4 hover:bg-white/5">
              <div className="text-[12px] font-semibold">Agent dashboard</div>
              <div className="mt-1 text-[11px] text-white/55">View your offline agent workspace</div>
            </a>
            <a href={route("console.agent.recharges")} className="rounded-2xl border border-white/10 bg-black/20 p-4 hover:bg-white/5">
              <div className="text-[12px] font-semibold">Recharges</div>
              <div className="mt-1 text-[11px] text-white/55">Track manual coin top-ups</div>
            </a>
            <a href={route("console.agent.withdrawals")} className="rounded-2xl border border-white/10 bg-black/20 p-4 hover:bg-white/5">
              <div className="text-[12px] font-semibold">Withdrawals</div>
              <div className="mt-1 text-[11px] text-white/55">Handle player withdrawals</div>
            </a>
            <a href={route("console.admin.overview")} className="rounded-2xl border border-white/10 bg-black/20 p-4 hover:bg-white/5">
              <div className="text-[12px] font-semibold">Admin overview</div>
              <div className="mt-1 text-[11px] text-white/55">Monitor agents and balances</div>
            </a>
          </div>
        </div>

      </div>

    </AdminLayout>
  );
}
