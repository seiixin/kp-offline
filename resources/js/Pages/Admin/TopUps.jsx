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

export default function TopUps({ active }) {
  return (
    <AdminLayout title="Agent top-ups" active={active}>
      
      <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div className="flex flex-1 items-center gap-2">
            <input
              className="w-full rounded-xl border border-white/10 bg-black/30 px-4 py-2 text-[12px] text-white placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-white/10"
              placeholder="Search agent or ref"
              disabled
            />
            <div className="shrink-0">
              <select
                className="rounded-xl border border-white/10 bg-black/30 px-4 py-2 text-[12px] text-white focus:outline-none focus:ring-2 focus:ring-white/10"
                disabled
              >
                <option>Status · All</option>
              </select>
            </div>
          </div>

          <button
            type="button"
            className="inline-flex items-center justify-center rounded-xl bg-emerald-500/90 px-4 py-2 text-[12px] font-semibold text-black hover:bg-emerald-400"
            disabled
          >
            New top-up (mock)
          </button>
        </div>

        <div className="mt-4 overflow-hidden rounded-xl border border-white/10">
          <table className="min-w-full text-left text-[12px]">
            <thead className="bg-white/5 text-white/45">
              <tr>
                <th className="px-4 py-3 font-semibold tracking-[0.20em] text-[10px]">AGENT</th>
<th className="px-4 py-3 font-semibold tracking-[0.20em] text-[10px]">REFERENCE</th>
<th className="px-4 py-3 font-semibold tracking-[0.20em] text-[10px]">AMOUNT</th>
<th className="px-4 py-3 font-semibold tracking-[0.20em] text-[10px]">STATUS</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-white/10">
              <tr className="bg-black/20">
                <td className="px-4 py-3 text-white/85">AGENT-001</td>
<td className="px-4 py-3 text-white/85">TOPUP-0001</td>
<td className="px-4 py-3 text-white/85">₱0.00</td>
<td className="px-4 py-3 text-white/85"><span className="rounded-full bg-white/10 px-2 py-1 text-[11px] text-white/70 ring-1 ring-white/10">Draft</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </AdminLayout>
  );
}
