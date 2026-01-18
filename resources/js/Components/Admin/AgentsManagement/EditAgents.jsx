import React, { useEffect, useState } from "react";
import axios from "axios";

export default function EditAgentModal({ agent, onClose, onSaved }) {
  const [form, setForm] = useState({
    name: agent.name || "",
    email: agent.email || "",
    password: "",
    role: agent.role || "agent",
    mongo_user_id: agent.mongo_user_id || "",
  });

  const [agencyMembers, setAgencyMembers] = useState([]);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    axios
      .get("/admin/agency-members-dropdown")
      .then((res) => setAgencyMembers(res.data ?? []));
  }, []);

  function update(key, value) {
    setForm((prev) => ({ ...prev, [key]: value }));
  }

  async function submit() {
    setSaving(true);
    try {
      await axios.put(`/admin/agents/${agent.id}`, {
        name: form.name,
        email: form.email,
        password: form.password || null,
        role: form.role,
        mongo_user_id: form.mongo_user_id || null,
      });

      onSaved();
      onClose();
    } catch (e) {
      alert("Failed to update user.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
      <div className="w-full max-w-md rounded-2xl bg-[#0B0F14] p-6">
        <h3 className="mb-4 text-sm font-semibold text-white">
          Edit ORC User
        </h3>

        <div className="space-y-3">
          <input
            value={form.name}
            onChange={(e) => update("name", e.target.value)}
            className="w-full rounded-xl bg-black/30 px-4 py-2 text-sm text-white"
            placeholder="Full name"
          />

          <input
            value={form.email}
            onChange={(e) => update("email", e.target.value)}
            className="w-full rounded-xl bg-black/30 px-4 py-2 text-sm text-white"
            placeholder="Email"
          />

          <input
            type="password"
            placeholder="New password (leave blank to keep)"
            value={form.password}
            onChange={(e) => update("password", e.target.value)}
            className="w-full rounded-xl bg-black/30 px-4 py-2 text-sm text-white"
          />

          {/* ROLE */}
          <select
            value={form.role}
            onChange={(e) => update("role", e.target.value)}
            className="w-full rounded-xl bg-black/30 px-4 py-2 text-sm text-white"
          >
            <option value="agent">Agent</option>
            <option value="admin">Admin</option>
          </select>

          {/* AGENCY LINK */}
          <select
            value={form.mongo_user_id || ""}
            onChange={(e) => update("mongo_user_id", e.target.value)}
            className="w-full rounded-xl bg-black/30 px-4 py-2 text-sm text-white"
          >
            <option value="">-- No agency link --</option>
            {agencyMembers.map((m) => (
              <option key={m.value} value={m.value}>
                {m.label}
              </option>
            ))}
          </select>
        </div>

        <div className="mt-5 flex justify-end gap-2">
          <button
            className="px-4 py-2 text-sm text-white/60"
            onClick={onClose}
          >
            Cancel
          </button>

          <button
            className="rounded-xl bg-sky-500 px-4 py-2 text-sm font-semibold text-black"
            onClick={submit}
            disabled={saving}
          >
            Update
          </button>
        </div>
      </div>
    </div>
  );
}
