import React, { useEffect, useMemo, useState } from "react";
import AdminLayout from "@/Layouts/AdminLayout";
import NewRechargeModal from "@/Components/Console/Recharges/NewRechargeModal";

function moneyUSDFromCents(cents) {
  const n = Number(cents || 0) / 100;
  try {
    return new Intl.NumberFormat("en-US", {
      style: "currency",
      currency: "USD",
      maximumFractionDigits: 2,
    }).format(n);
  } catch {
    return `$${n.toFixed(2)}`;
  }
}

function StatusPill({ status }) {
  const s = String(status || "").toLowerCase();
  const cls =
    s === "completed" || s === "successful"
      ? "bg-emerald-500/15 text-emerald-200 ring-emerald-500/20"
      : s === "failed"
      ? "bg-rose-500/15 text-rose-200 ring-rose-500/20"
      : "bg-white/10 text-white/70 ring-white/10";

  const label = s ? s[0].toUpperCase() + s.slice(1) : "—";

  return (
    <span className={`rounded-full px-2 py-1 text-[11px] ring-1 ${cls}`}>
      {label}
    </span>
  );
}

export default function Index({ active }) {
  const [open, setOpen] = useState(false);

  const [filters, setFilters] = useState({
    q: "",
    status: "all",
    per_page: 15,
    page: 1,
  });

  const [loading, setLoading] = useState(false);
  const [rows, setRows] = useState(null);
  const [error, setError] = useState("");

  const fetchList = async (next = filters) => {
    setLoading(true);
    setError("");

    const params = new URLSearchParams();
    if (next.q) params.set("q", next.q);
    if (next.status && next.status !== "all") params.set("status", next.status);
    if (next.per_page) params.set("per_page", String(next.per_page));
    if (next.page) params.set("page", String(next.page));

    try {
      const res = await fetch(`${route("console.agent.recharges.list")}?${params.toString()}`, {
        headers: { Accept: "application/json" },
        credentials: "same-origin",
      });

      const data = await res.json();
      if (!res.ok) {
        throw new Error(data?.error || "Failed to load recharges.");
      }
      setRows(data.rows);
    } catch (e) {
      setError(e.message || "Failed to load recharges.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchList(filters);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const links = rows?.links || [];
  const dataRows = rows?.data || [];

  return (
    <AdminLayout title="Offline recharges" active={active}>
      <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-4 shadow-[0_0_0_1px_rgba(255,255,255,0.02)]">
        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div className="flex flex-1 items-center gap-2">
            <input
              className="w-full rounded-xl border border-white/10 bg-black/30 px-4 py-2 text-[12px] text-white placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-white/10"
              placeholder="Search mongo user / ref / key"
              value={filters.q}
              onChange={(e) => setFilters((p) => ({ ...p, q: e.target.value }))}
              onKeyDown={(e) => {
                if (e.key === "Enter") {
                  const next = { ...filters, page: 1 };
                  setFilters(next);
                  fetchList(next);
                }
              }}
            />
            <div className="shrink-0">
              <select
                className="rounded-xl border border-white/10 bg-black/30 px-4 py-2 text-[12px] text-white focus:outline-none focus:ring-2 focus:ring-white/10"
                value={filters.status}
                onChange={(e) => {
                  const next = { ...filters, status: e.target.value, page: 1 };
                  setFilters(next);
                  fetchList(next);
                }}
              >
                <option value="all">Status · All</option>
                <option value="processing">Processing</option>
                <option value="completed">Completed</option>
                <option value="failed">Failed</option>
              </select>
            </div>

            <button
              type="button"
              className="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-[12px] text-white/80 hover:bg-white/10"
              onClick={() => fetchList({ ...filters, page: 1 })}
              disabled={loading}
            >
              {loading ? "Loading..." : "Search"}
            </button>
          </div>

          <button
            type="button"
            className="inline-flex items-center justify-center rounded-xl bg-emerald-500/90 px-4 py-2 text-[12px] font-semibold text-black hover:bg-emerald-400"
            onClick={() => setOpen(true)}
          >
            New recharge
          </button>
        </div>

        {error ? (
          <div className="mt-3 rounded-xl border border-rose-500/20 bg-rose-500/10 px-3 py-2 text-[12px] text-rose-100">
            {error}
          </div>
        ) : null}

        <div className="mt-4 overflow-hidden rounded-xl border border-white/10">
          <table className="min-w-full text-left text-[12px]">
            <thead className="bg-white/5 text-white/45">
              <tr>
                <th className="px-4 py-3 text-[10px] font-semibold tracking-[0.20em]">MONGO USER ID</th>
                <th className="px-4 py-3 text-[10px] font-semibold tracking-[0.20em]">COINS</th>
                <th className="px-4 py-3 text-[10px] font-semibold tracking-[0.20em]">AMOUNT</th>
                <th className="px-4 py-3 text-[10px] font-semibold tracking-[0.20em]">METHOD</th>
                <th className="px-4 py-3 text-[10px] font-semibold tracking-[0.20em]">REFERENCE</th>
                <th className="px-4 py-3 text-[10px] font-semibold tracking-[0.20em]">STATUS</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-white/10">
              {dataRows.length === 0 ? (
                <tr className="bg-black/20">
                  <td className="px-4 py-4 text-white/60" colSpan={6}>
                    {loading ? "Loading..." : "No recharges found."}
                  </td>
                </tr>
              ) : (
                dataRows.map((r) => (
                  <tr key={r.id} className="bg-black/20">
                    <td className="px-4 py-3 font-mono text-[11px] text-white/85">{r.mongo_user_id}</td>
                    <td className="px-4 py-3 text-white/85">{Number(r.coins_amount || 0).toLocaleString()}</td>
                    <td className="px-4 py-3 text-white/85">{moneyUSDFromCents(r.amount_usd_cents)}</td>
                    <td className="px-4 py-3 text-white/85">{r.method || "—"}</td>
                    <td className="px-4 py-3 text-white/85">{r.reference || "—"}</td>
                    <td className="px-4 py-3 text-white/85">
                      <StatusPill status={r.status} />
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {rows ? (
          <div className="mt-4 flex flex-wrap items-center justify-between gap-2 text-[12px] text-white/60">
            <div>
              Showing {rows.from || 0}–{rows.to || 0} of {rows.total || 0}
            </div>
            <div className="flex flex-wrap gap-2">
              <button
                className="rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 hover:bg-white/10 disabled:opacity-50"
                disabled={!rows.prev_page_url || loading}
                onClick={() => {
                  const next = { ...filters, page: Math.max(1, (rows.current_page || 1) - 1) };
                  setFilters(next);
                  fetchList(next);
                }}
              >
                Prev
              </button>
              <button
                className="rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 hover:bg-white/10 disabled:opacity-50"
                disabled={!rows.next_page_url || loading}
                onClick={() => {
                  const next = { ...filters, page: (rows.current_page || 1) + 1 };
                  setFilters(next);
                  fetchList(next);
                }}
              >
                Next
              </button>
            </div>
          </div>
        ) : null}
      </div>

      <NewRechargeModal
        open={open}
        onClose={() => setOpen(false)}
        onCreated={() => {
          setOpen(false);
          const next = { ...filters, page: 1 };
          setFilters(next);
          fetchList(next);
        }}
      />
    </AdminLayout>
  );
}
