import React, { useEffect, useState } from "react";
import AdminLayout from "@/Layouts/AdminLayout";
import axios from "axios";
import CreateWithdrawalModal from "@/Components/Admin/Withdrawals/CreateWithdrawalModal.jsx";

/* ---------------- STATUS BADGE ---------------- */

function StatusBadge({ status }) {
  const map = {
    processing: "bg-yellow-500/20 text-yellow-300",
    successful: "bg-emerald-500/20 text-emerald-300",
    cancelled: "bg-white/10 text-white/60",
    failed: "bg-red-500/20 text-red-300",
  };

  return (
    <span
      className={`rounded-full px-2 py-1 text-[11px] ring-1 ring-white/10 ${
        map[status] ?? "bg-white/10 text-white/70"
      }`}
    >
      {status}
    </span>
  );
}

/* ---------------- EDIT MODAL ---------------- */

function EditModal({ row, onClose, onSaved }) {
  const [reference, setReference] = useState(row.reference ?? "");
  const [notes, setNotes] = useState(row.notes ?? "");
  const [loading, setLoading] = useState(false);

  async function submit() {
    setLoading(true);
    try {
      await axios.put(`/agent/withdrawals/${row.id}`, {
        reference,
        notes,
        reason: "edited from admin withdrawals page",
      });
      onSaved();
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70">
      <div className="w-full max-w-md rounded-2xl border border-white/10 bg-[#0f0f0f] p-5">
        <div className="text-sm font-semibold text-white">Edit Withdrawal</div>

        <div className="mt-4 space-y-3">
          <input
            className="w-full rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-[12px] text-white"
            placeholder="Reference"
            value={reference}
            onChange={(e) => setReference(e.target.value)}
          />

          <textarea
            rows={3}
            className="w-full rounded-xl border border-white/10 bg-black/40 px-3 py-2 text-[12px] text-white"
            placeholder="Notes"
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
          />
        </div>

        <div className="mt-4 flex justify-end gap-2">
          <button
            onClick={onClose}
            className="rounded-lg border border-white/10 px-3 py-1.5 text-[12px] text-white/70"
          >
            Cancel
          </button>

          <button
            onClick={submit}
            disabled={loading}
            className="rounded-lg bg-emerald-500 px-3 py-1.5 text-[12px] font-semibold text-black"
          >
            Save
          </button>
        </div>
      </div>
    </div>
  );
}

/* ---------------- MAIN PAGE ---------------- */

export default function Withdrawals({ active }) {
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);

  const [editRow, setEditRow] = useState(null);
  const [showCreate, setShowCreate] = useState(false);

  async function load() {
    setLoading(true);
    try {
      const res = await axios.get("/agent/withdrawals/list");
      setRows(res.data?.data?.data ?? []);
    } finally {
      setLoading(false);
    }
  }

  async function cancelRow(row) {
    if (!confirm("Cancel this withdrawal?")) return;

    await axios.delete(`/agent/withdrawals/${row.id}`, {
      data: { reason: "cancelled from admin withdrawals page" },
    });

    load();
  }

  useEffect(() => {
    load();
  }, []);

  return (
    <AdminLayout title="Withdrawals" active={active}>
      <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
        <div className="mb-4 flex items-center justify-between">
          <div className="text-sm font-semibold text-white">
            Withdrawal Requests
          </div>

          <button
            onClick={() => setShowCreate(true)}
            className="rounded-xl bg-emerald-500/90 px-4 py-2 text-[12px] font-semibold text-black hover:bg-emerald-400"
          >
            New Withdrawal
          </button>
        </div>

        <div className="overflow-hidden rounded-xl border border-white/10">
          <table className="min-w-full text-left text-[12px]">
            <thead className="bg-white/5 text-white/45">
              <tr>
                <th className="px-4 py-3 text-[10px] tracking-[0.2em]">
                  PLAYER
                </th>
                <th className="px-4 py-3 text-[10px] tracking-[0.2em]">
                  AMOUNT
                </th>
                <th className="px-4 py-3 text-[10px] tracking-[0.2em]">
                  CHANNEL
                </th>
                <th className="px-4 py-3 text-[10px] tracking-[0.2em]">
                  REFERENCE
                </th>
                <th className="px-4 py-3 text-[10px] tracking-[0.2em]">
                  STATUS
                </th>
                <th className="px-4 py-3"></th>
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

              {!loading && rows.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-4 py-6 text-white/40">
                    No withdrawals
                  </td>
                </tr>
              )}

              {rows.map((row) => {
                const fullName = row.player?.full_name;
                const username = row.player?.username;

                return (
                  <tr key={row.id} className="bg-black/20">
                    {/* PLAYER */}
                    <td className="px-4 py-3">
                      <div className="text-white/90">
                        {fullName ||
                          username ||
                          row.user_identification ||
                          "—"}
                      </div>

                      {(username || row.user_identification) && (
                        <div className="text-[10px] text-white/40">
                          @{username || row.user_identification}
                        </div>
                      )}
                    </td>

                    {/* AMOUNT */}
                    <td className="px-4 py-3 text-white/85">
                      ₱{(row.payout_cents / 100).toFixed(2)}
                    </td>

                    {/* CHANNEL */}
                    <td className="px-4 py-3 text-white/85">
                      {row.payout_method || "—"}
                    </td>

                    {/* REFERENCE */}
                    <td className="px-4 py-3 text-white/85">
                      {row.reference || "—"}
                    </td>

                    {/* STATUS */}
                    <td className="px-4 py-3">
                      <StatusBadge status={row.status} />
                    </td>

                    {/* ACTIONS */}
                    <td className="px-4 py-3">
                      <div className="flex gap-2">
                        <button
                          onClick={() => setEditRow(row)}
                          className="text-[11px] text-blue-400 hover:underline"
                        >
                          Edit
                        </button>

                        <button
                          onClick={() => cancelRow(row)}
                          className="text-[11px] text-red-400 hover:underline"
                        >
                          Delete
                        </button>
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      {/* CREATE */}
      {showCreate && (
        <CreateWithdrawalModal
          onClose={() => setShowCreate(false)}
          onSaved={() => {
            setShowCreate(false);
            load();
          }}
        />
      )}

      {/* EDIT */}
      {editRow && (
        <EditModal
          row={editRow}
          onClose={() => setEditRow(null)}
          onSaved={() => {
            setEditRow(null);
            load();
          }}
        />
      )}
    </AdminLayout>
  );
}
