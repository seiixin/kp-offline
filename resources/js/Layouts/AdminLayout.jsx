import React from "react";
import { Head } from "@inertiajs/react";
import Sidebar from "@/Components/Admin/Sidebar";

export default function AdminLayout({ title, active = "dashboard", children }) {
  return (
    <div className="min-h-screen bg-gradient-to-b from-[#05060c] via-[#070819] to-[#04040a] text-white">
      <Head title={title ? `${title} · KittyParty` : "KittyParty"} />

      <div className="flex min-h-screen">
        <Sidebar active={active} />

        <main className="flex-1">
          <div className="mx-auto w-[min(1120px,calc(100%-32px))] py-8">
            {title ? (
              <div className="mb-6">
                <h1 className="text-[22px] font-semibold tracking-tight">{title}</h1>
              </div>
            ) : null}

            {children}
          </div>
        </main>
      </div>
    </div>
  );
}
