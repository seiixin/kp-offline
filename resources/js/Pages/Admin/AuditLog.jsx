import React, { useEffect, useState } from "react";
import axios from "axios";
import AdminLayout from "@/Layouts/AdminLayout";

/* =====================================================
 | UI HELPERS
 ===================================================== */
function Card({ title, desc, right }) {
  return (
    <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-5 shadow-[0_0_0_1px_rgba(255,255,255,0.02)]">
      <div className="flex items-start justify-between gap-4">
        <div>
          <div className="text-[13px] font-semibold text-white/90">{title}</div>
          {desc && <div className="mt-1 text-[12px] text-white/55">{desc}</div>}
        </div>
        {right}
      </div>
    </div>
  );
}

/* =====================================================
 | PAGE
 ===================================================== */
export default function AuditLog({ active }) {
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(false);

  /* =========================
   | FILTERS
   ========================= */
  const [q, setQ] = useState("");
  const [type, setType] = useState("");
  const [page, setPage] = useState(1);

  /* =========================
   | FETCH
   ========================= */
  async function fetchLogs() {
    setLoading(true);
    try {
      const res = await axios.get("/admin/audit-logs", {
        params: {
          q,
          entity_type: type || undefined,
          page,
        },
      });

      setLogs(res.data.data.data || []);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    fetchLogs();
  }, [q, type, page]);

  /* =========================
   | EXPORTS
   ========================= */
  function exportExcel() {
    window.open(
      `/admin/audit-logs/export/excel?q=${q}&entity_type=${type}`,
      "_blank"
    );
  }

  function exportPdf() {
    window.open(
      `/admin/audit-logs/export/pdf?q=${q}&entity_type=${type}`,
      "_blank"
    );
  }

  return (
    <AdminLayout title="Audit log" active={active}>
      {/* =========================
         | CONTROLS
         ========================= */}
      <Card
        title="Audit log"
        desc="System activity, financial events, and administrative actions"
        right={
          <div className="flex gap-2">
            <button
              onClick={exportExcel}
              className="rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-[11px] text-white hover:bg-white/10"
            >
              Export Excel
            </button>
            <button
              onClick={exportPdf}
              className="rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-[11px] text-white hover:bg-white/10"
            >
              Export PDF
            </button>
          </div>
        }
      />

      {/* =========================
         | SEARCH / FILTER
         ========================= */}
      <div className="mt-4 rounded-2xl border border-white/10 bg-white/[0.03] p-4">
        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div className="flex flex-1 items-center gap-2">
            <input
              value={q}
              onChange={(e) => setQ(e.target.value)}
              className="w-full rounded-xl border border-white/10 bg-black/30 px-4 py-2 text-[12px] text-white placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-white/10"
              placeholder="Search by actor, action, or detail"
            />

            <select
              value={type}
              onChange={(e) => setType(e.target.value)}
              className="rounded-xl border border-white/10 bg-black/30 px-4 py-2 text-[12px] text-white focus:outline-none focus:ring-2 focus:ring-white/10"
            >
              <option value="">Type · All</option>
              <option value="offline_recharge">Offline Recharge</option>
              <option value="offline_withdrawal">Offline Withdrawal</option>
              <option value="wallet">Wallet</option>
              <option value="system">System</option>
            </select>
          </div>
        </div>

        {/* =========================
           | TABLE
           ========================= */}
        <div className="mt-4 overflow-hidden rounded-xl border border-white/10">
          <table className="min-w-full text-left text-[12px]">
            <thead className="bg-white/5 text-white/45">
              <tr>
                <th className="px-4 py-3 text-[10px] font-semibold tracking-[0.20em]">
                  WHEN
                </th>
                <th className="px-4 py-3 text-[10px] font-semibold tracking-[0.20em]">
                  ACTOR
                </th>
                <th className="px-4 py-3 text-[10px] font-semibold tracking-[0.20em]">
                  TYPE
                </th>
                <th className="px-4 py-3 text-[10px] font-semibold tracking-[0.20em]">
                  DETAIL
                </th>
              </tr>
            </thead>

            <tbody className="divide-y divide-white/10">
              {loading && (
                <tr>
                  <td
                    colSpan={4}
                    className="px-4 py-6 text-center text-white/40"
                  >
                    Loading…
                  </td>
                </tr>
              )}

              {!loading && logs.length === 0 && (
                <tr>
                  <td
                    colSpan={4}
                    className="px-4 py-6 text-center text-white/40"
                  >
                    No audit logs found.
                  </td>
                </tr>
              )}

              {!loading &&
                logs.map((log) => (
                  <tr key={log.id} className="bg-black/20">
                    <td className="px-4 py-3 text-white/65">
                      {log.when}
                    </td>
                    <td className="px-4 py-3 text-white/85">
                      {log.actor}
                    </td>
                    <td className="px-4 py-3 text-white/85">
                      {log.entity_type}
                    </td>
                    <td className="px-4 py-3 text-white/85">
                      {log.action}
                    </td>
                  </tr>
                ))}
            </tbody>
          </table>
        </div>
      </div>
    </AdminLayout>
  );
}
