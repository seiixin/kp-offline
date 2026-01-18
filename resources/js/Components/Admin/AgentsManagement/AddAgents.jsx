import React, { useEffect, useState } from "react";
import axios from "axios";

export default function AddAgentModal({ onClose, onSaved }) {
  const [form, setForm] = useState({
    name: "",
    email: "",
    password: "",
    role: "agent",
    mongo_user_id: "",
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
      await axios.post("/admin/agents", form);
      onSaved();
      onClose();
    } catch (e) {
      alert("Failed to create agent.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
      <div className="w-full max-w-md rounded-2xl bg-[#0B0F14] p-6">
        <h3 className="mb-4 text-sm font-semibold text-white">
          Add ORC User
        </h3>

        <div className="space-y-3">
          <input
            placeholder="Full name"
            className="w-full rounded-xl bg-black/30 px-4 py-2 text-sm text-white"
            onChange={(e) => update("name", e.target.value)}
          />

          <input
            placeholder="Email"
            type="email"
            className="w-full rounded-xl bg-black/30 px-4 py-2 text-sm text-white"
            onChange={(e) => update("email", e.target.value)}
          />

          <input
            placeholder="Password"
            type="password"
            className="w-full rounded-xl bg-black/30 px-4 py-2 text-sm text-white"
            onChange={(e) => update("password", e.target.value)}
          />

          {/* ROLE */}
          <select
            className="w-full rounded-xl bg-black/30 px-4 py-2 text-sm text-white"
            value={form.role}
            onChange={(e) => update("role", e.target.value)}
          >
            <option value="agent">Agent</option>
            <option value="admin">Admin</option>
          </select>

          {/* AGENCY MEMBER LINK */}
          <select
            className="w-full rounded-xl bg-black/30 px-4 py-2 text-sm text-white"
            onChange={(e) => update("mongo_user_id", e.target.value)}
          >
            <option value="">-- Link agency member (optional) --</option>
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
            className="rounded-xl bg-emerald-500 px-4 py-2 text-sm font-semibold text-black"
            onClick={submit}
            disabled={saving}
          >
            Save
          </button>
        </div>
      </div>
    </div>
  );
}
