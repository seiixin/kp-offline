import React, { useEffect, useState } from "react";
import AdminLayout from "@/Layouts/AdminLayout";
import axios from "axios";

import AddAgentModal from "@/Components/Admin/AgentsManagement/AddAgents";
import EditAgentModal from "@/Components/Admin/AgentsManagement/EditAgents";

export default function Agents() {
  const [agents, setAgents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const [showAdd, setShowAdd] = useState(false);
  const [editAgent, setEditAgent] = useState(null);

  /* ===============================
     LOAD AGENTS (MYSQL USERS)
  =============================== */
  const loadAgents = async () => {
    setLoading(true);
    setError(null);

    try {
      const res = await axios.get("/admin/agents");
      setAgents(res.data.data ?? []);
    } catch (e) {
      setError("Failed to load agents.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadAgents();
  }, []);

  /* ===============================
     DELETE AGENT
  =============================== */
  const deleteAgent = async (agent) => {
    const ok = window.confirm(
      `Delete agent "${agent.name}"?\n\nThis action cannot be undone.`
    );

    if (!ok) return;

    try {
      await axios.delete(`/admin/agents/${agent.id}`);
      loadAgents();
    } catch (e) {
      alert("Failed to delete agent.");
    }
  };

  return (
    <AdminLayout title="Agents" active="agents">
      <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-4">

        {/* HEADER */}
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-sm font-semibold text-white/90">
            ORC Agents
          </h2>

          <button
            className="rounded-xl bg-emerald-500/90 px-4 py-2 text-[12px] font-semibold text-black"
            onClick={() => setShowAdd(true)}
          >
            Add Agent
          </button>
        </div>

        {/* STATES */}
        {loading && (
          <div className="py-10 text-center text-white/40 text-sm">
            Loading agents…
          </div>
        )}

        {error && (
          <div className="py-10 text-center text-red-400 text-sm">
            {error}
          </div>
        )}

        {!loading && !error && agents.length === 0 && (
          <div className="py-10 text-center text-white/40 text-sm">
            No agents found.
          </div>
        )}

        {/* TABLE */}
        {!loading && !error && agents.length > 0 && (
          <div className="overflow-hidden rounded-xl border border-white/10">
            <table className="min-w-full text-left text-[12px]">
              <thead className="bg-white/5 text-white/50">
                <tr>
                  <th className="px-4 py-3 text-[10px] tracking-widest">ID</th>
                  <th className="px-4 py-3 text-[10px] tracking-widest">NAME</th>
                  <th className="px-4 py-3 text-[10px] tracking-widest">EMAIL</th>
                  <th className="px-4 py-3 text-[10px] tracking-widest">AGENCY LINK</th>
                  <th className="px-4 py-3 text-[10px] tracking-widest">ACTION</th>
                </tr>
              </thead>

              <tbody className="divide-y divide-white/10">
                {agents.map((agent) => (
                  <tr key={agent.id} className="bg-black/20">
                    <td className="px-4 py-3 text-white/70">{agent.id}</td>
                    <td className="px-4 py-3 text-white/90">{agent.name}</td>
                    <td className="px-4 py-3 text-white/60">{agent.email}</td>
                    <td className="px-4 py-3">
                      {agent.mongo_user_id ? (
                        <span className="rounded-full bg-sky-500/20 px-2 py-1 text-[11px] text-sky-200">
                          Linked
                        </span>
                      ) : (
                        <span className="rounded-full bg-amber-500/20 px-2 py-1 text-[11px] text-amber-200">
                          Not linked
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex gap-3">
                        <button
                          className="text-[11px] font-semibold text-sky-300 hover:underline"
                          onClick={() => setEditAgent(agent)}
                        >
                          Edit
                        </button>

                        <button
                          className="text-[11px] font-semibold text-rose-400 hover:underline"
                          onClick={() => deleteAgent(agent)}
                        >
                          Delete
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* MODALS */}
      {showAdd && (
        <AddAgentModal
          onClose={() => setShowAdd(false)}
          onSaved={loadAgents}
        />
      )}

      {editAgent && (
        <EditAgentModal
          agent={editAgent}
          onClose={() => setEditAgent(null)}
          onSaved={loadAgents}
        />
      )}
    </AdminLayout>
  );
}
