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

export default function AdminOverview({ active }) {
  return (
    <AdminLayout title="Admin overview" active={active}>
      
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <a href={route("console.admin.agents")} className="rounded-2xl border border-white/10 bg-white/[0.03] p-5 hover:bg-white/5">
          <div className="text-[12px] font-semibold">Agents</div>
          <div className="mt-1 text-[11px] text-white/55">View all offline recharge agents.</div>
        </a>
        <a href={route("console.admin.topups")} className="rounded-2xl border border-white/10 bg-white/[0.03] p-5 hover:bg-white/5">
          <div className="text-[12px] font-semibold">Top-ups</div>
          <div className="mt-1 text-[11px] text-white/55">Track credits loaded to agents.</div>
        </a>
        <a href={route("console.admin.auditlog")} className="rounded-2xl border border-white/10 bg-white/[0.03] p-5 hover:bg-white/5">
          <div className="text-[12px] font-semibold">Audit log</div>
          <div className="mt-1 text-[11px] text-white/55">See important events and adjustments.</div>
        </a>
      </div>

    </AdminLayout>
  );
}
