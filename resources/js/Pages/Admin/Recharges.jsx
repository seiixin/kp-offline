import React, { useEffect, useState } from "react";
import AdminLayout from "@/Layouts/AdminLayout";
import NewRechargeModal from "@/Components/Console/Recharges/NewRechargeModal";

/* ---------------- HELPERS ---------------- */

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

function PlayerCell({ player, mongoUserId }) {
  if (!player) {
    return <span className="text-white/40">—</span>;
  }

  return (
    <div className="leading-tight">
      <div className="text-white/90">
        {player.full_name || "—"}
      </div>
      {player.username && (
        <div className="text-[11px] text-white/40">
          @{player.username}
        </div>
      )}
    </div>
  );
}

/* ---------------- PAGE ---------------- */

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

  async function fetchList(next = filters) {
    setLoading(true);
    setError("");

    const params = new URLSearchParams();
    if (next.q) params.set("q", next.q);
    if (next.status && next.status !== "all") params.set("status", next.status);
    if (next.per_page) params.set("per_page", String(next.per_page));
    if (next.page) params.set("page", String(next.page));

    try {
      const res = await fetch(
        `${route("console.agent.recharges.list")}?${params.toString()}`,
        { headers: { Accept: "application/json" }, credentials: "same-origin" }
      );

      const json = await res.json();
      if (!res.ok) throw new Error(json?.error || "Failed to load recharges.");

      setRows(json.data ?? json.rows ?? null);
    } catch (e) {
      setError(e.message || "Failed to load recharges.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    fetchList(filters);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const dataRows = rows?.data || [];

  return (
    <AdminLayout title="Offline recharges" active={active}>
      <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
        {/* HEADER */}
        <div className="flex items-center justify-between mb-4">
          <div className="text-sm font-semibold text-white">
            Offline Recharges
          </div>
          <button
            className="rounded-xl bg-emerald-500/90 px-4 py-2 text-[12px] font-semibold text-black hover:bg-emerald-400"
            onClick={() => setOpen(true)}
          >
            New recharge
          </button>
        </div>

        {error && (
          <div className="mb-3 rounded-xl border border-rose-500/20 bg-rose-500/10 px-3 py-2 text-[12px] text-rose-100">
            {error}
          </div>
        )}

        {/* TABLE */}
        <div className="overflow-hidden rounded-xl border border-white/10">
          <table className="min-w-full text-left text-[12px]">
            <thead className="bg-white/5 text-white/45">
              <tr>
                <th className="px-4 py-3 text-[10px] tracking-[0.2em]">PLAYER</th>
                <th className="px-4 py-3 text-[10px] tracking-[0.2em]">COINS</th>
                <th className="px-4 py-3 text-[10px] tracking-[0.2em]">AMOUNT</th>
                <th className="px-4 py-3 text-[10px] tracking-[0.2em]">METHOD</th>
                <th className="px-4 py-3 text-[10px] tracking-[0.2em]">REFERENCE</th>
                <th className="px-4 py-3 text-[10px] tracking-[0.2em]">STATUS</th>
              </tr>
            </thead>

            <tbody className="divide-y divide-white/10">
              {loading && (
                <tr>
                  <td colSpan={6} className="px-4 py-6 text-white/40">
                    Loading…
                  </td>
                </tr>
              )}

              {!loading && dataRows.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-4 py-6 text-white/40">
                    No recharges found.
                  </td>
                </tr>
              )}

              {dataRows.map((r) => (
                <tr key={r.id} className="bg-black/20">
                  <td className="px-4 py-3">
                    <PlayerCell
                      player={r.player}
                      mongoUserId={r.mongo_user_id}
                    />
                  </td>

                  <td className="px-4 py-3 text-white/85">
                    {Number(r.coins_amount || 0).toLocaleString()}
                  </td>

                  <td className="px-4 py-3 text-white/85">
                    {moneyUSDFromCents(r.amount_usd_cents)}
                  </td>

                  <td className="px-4 py-3 text-white/85">
                    {r.method || "—"}
                  </td>

                  <td className="px-4 py-3 text-white/85">
                    {r.reference || "—"}
                  </td>

                  <td className="px-4 py-3">
                    <StatusPill status={r.status} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      <NewRechargeModal
        open={open}
        onClose={() => setOpen(false)}
        onCreated={() => {
          setOpen(false);
          fetchList({ ...filters, page: 1 });
        }}
      />
    </AdminLayout>
  );
}
