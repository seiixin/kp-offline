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

export default function Wallet({ active }) {
  return (
    <AdminLayout title="Agent wallet" active={active}>
      
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
          <div className="text-[10px] font-semibold tracking-[0.28em] text-white/40">AVAILABLE</div>
          <div className="mt-3 text-3xl font-semibold text-emerald-200">₱0.00</div>
        </div>
        <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
          <div className="text-[10px] font-semibold tracking-[0.28em] text-white/40">RESERVED</div>
          <div className="mt-3 text-3xl font-semibold text-amber-200">₱0.00</div>
        </div>
        <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
          <div className="text-[10px] font-semibold tracking-[0.28em] text-white/40">ACTIONS</div>
          <div className="mt-3 flex gap-2">
            <button className="rounded-xl bg-white/10 px-4 py-2 text-[12px] text-white/80 ring-1 ring-white/10" disabled>
              Add funds (mock)
            </button>
            <button className="rounded-xl bg-white/10 px-4 py-2 text-[12px] text-white/80 ring-1 ring-white/10" disabled>
              Request payout (mock)
            </button>
          </div>
        </div>
      </div>

      <div className="mt-6 overflow-hidden rounded-2xl border border-white/10 bg-white/[0.03]">
        <table className="min-w-full text-left text-[12px]">
          <thead className="bg-white/5 text-white/45">
            <tr>
              <th className="px-4 py-3 font-semibold tracking-[0.20em] text-[10px]">TYPE</th>
              <th className="px-4 py-3 font-semibold tracking-[0.20em] text-[10px]">DIRECTION</th>
              <th className="px-4 py-3 font-semibold tracking-[0.20em] text-[10px]">AMOUNT</th>
              <th className="px-4 py-3 font-semibold tracking-[0.20em] text-[10px]">NOTE</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-white/10">
            <tr className="bg-black/20">
              <td className="px-4 py-3 text-white/85">Sample</td>
              <td className="px-4 py-3 text-white/85">+</td>
              <td className="px-4 py-3 text-white/85">₱0.00</td>
              <td className="px-4 py-3 text-white/65">Wireframe only</td>
            </tr>
          </tbody>
        </table>
      </div>

    </AdminLayout>
  );
}
