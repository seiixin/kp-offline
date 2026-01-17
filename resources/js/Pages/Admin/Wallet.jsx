import React, { useEffect, useState } from "react";
import AdminLayout from "@/Layouts/AdminLayout";
import axios from "axios";

/* ================= HELPERS ================= */

function formatAmount(cents) {
  return `₱${(Number(cents || 0) / 100).toFixed(2)}`;
}

/* ================= CARD ================= */

function Card({ title, desc, right, children }) {
  return (
    <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-5 shadow-[0_0_0_1px_rgba(255,255,255,0.02)]">
      <div className="flex items-start justify-between gap-4">
        <div>
          <div className="text-[13px] font-semibold text-white/90">
            {title}
          </div>
          {desc && (
            <div className="mt-1 text-[12px] text-white/55">
              {desc}
            </div>
          )}
        </div>
        {right}
      </div>
      {children}
    </div>
  );
}

/* ================= PAGE ================= */

export default function Wallet({ active }) {
  const [summary, setSummary] = useState(null);
  const [ledger, setLedger] = useState([]);
  const [loading, setLoading] = useState(true);

  async function load() {
    setLoading(true);

    try {
      // ensure wallet exists (idempotent)
      await axios.post("/agent/wallets/ensure-diamonds", {}, {
        withCredentials: true,
      });

      const [summaryRes, ledgerRes] = await Promise.all([
        axios.get("/agent/wallet/summary", { withCredentials: true }),
        axios.get("/agent/wallet/ledger", { withCredentials: true }),
      ]);

      setSummary(summaryRes.data?.data ?? null);
      setLedger(ledgerRes.data?.data ?? []);
    } catch {
      setSummary(null);
      setLedger([]);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    load();
  }, []);

  return (
    <AdminLayout title="Agent wallet" active={active}>
      {/* ================= SUMMARY ================= */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <Card title="AVAILABLE">
          <div className="mt-3 text-3xl font-semibold text-emerald-200">
            {loading ? "—" : formatAmount(summary?.available_cents)}
          </div>
        </Card>

        <Card title="RESERVED">
          <div className="mt-3 text-3xl font-semibold text-amber-200">
            {loading ? "—" : formatAmount(summary?.reserved_cents)}
          </div>
        </Card>

        <Card title="ACTIONS">
          <div className="mt-3 flex gap-2">
            <button
              disabled
              className="rounded-xl bg-white/10 px-4 py-2 text-[12px] text-white/80 ring-1 ring-white/10"
            >
              Add funds
            </button>
            <button
              disabled
              className="rounded-xl bg-white/10 px-4 py-2 text-[12px] text-white/80 ring-1 ring-white/10"
            >
              Request payout
            </button>
          </div>
        </Card>
      </div>

      {/* ================= LEDGER ================= */}
      <div className="mt-6 overflow-hidden rounded-2xl border border-white/10 bg-white/[0.03]">
        <table className="min-w-full text-left text-[12px]">
          <thead className="bg-white/5 text-white/45">
            <tr>
              <th className="px-4 py-3 tracking-[0.20em] text-[10px]">
                TYPE
              </th>
              <th className="px-4 py-3 tracking-[0.20em] text-[10px]">
                DIRECTION
              </th>
              <th className="px-4 py-3 tracking-[0.20em] text-[10px]">
                AMOUNT
              </th>
              <th className="px-4 py-3 tracking-[0.20em] text-[10px]">
                NOTE
              </th>
            </tr>
          </thead>

          <tbody className="divide-y divide-white/10">
            {loading && (
              <tr>
                <td
                  colSpan={4}
                  className="px-4 py-6 text-white/40"
                >
                  Loading…
                </td>
              </tr>
            )}

            {!loading && ledger.length === 0 && (
              <tr>
                <td
                  colSpan={4}
                  className="px-4 py-6 text-white/40"
                >
                  No ledger entries
                </td>
              </tr>
            )}

            {ledger.map((row) => (
              <tr key={row.id} className="bg-black/20">
                <td className="px-4 py-3 text-white/85">
                  {row.event_type}
                </td>

                <td className="px-4 py-3 text-white/85">
                  {row.direction === "credit" ? "+" : "−"}
                </td>

                <td className="px-4 py-3 text-white/85">
                  {formatAmount(row.amount_cents)}
                </td>

                <td className="px-4 py-3 text-white/65">
                  {row.meta?.note || "—"}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </AdminLayout>
  );
}
