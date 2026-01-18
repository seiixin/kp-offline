import React from "react";
import { Link, usePage } from "@inertiajs/react";

function cx(...xs) {
  return xs.filter(Boolean).join(" ");
}

function Icon({ children }) {
  return (
    <span className="grid h-9 w-9 place-items-center rounded-full bg-white/5 ring-1 ring-white/10">
      <svg viewBox="0 0 24 24" className="h-4.5 w-4.5" fill="none" stroke="currentColor" strokeWidth="2">
        {children}
      </svg>
    </span>
  );
}

function NavItem({ href, label, active, icon, badge }) {
  return (
    <Link
      href={href}
      className={cx(
        "group flex items-center gap-3 rounded-xl px-3 py-2 text-[13px] transition",
        active ? "bg-white/8 ring-1 ring-white/10" : "hover:bg-white/6"
      )}
    >
      <div className={cx(active ? "text-emerald-300" : "text-white/70 group-hover:text-white")}>
        {icon}
      </div>
      <div className="flex-1">
        <div className={cx("leading-none", active ? "text-white" : "text-white/80 group-hover:text-white")}>
          {label}
        </div>
      </div>
      {badge ? (
        <span className="rounded-full bg-white/10 px-2 py-0.5 text-[11px] text-white/70 ring-1 ring-white/10">
          {badge}
        </span>
      ) : null}
    </Link>
  );
}

export default function Sidebar({ active }) {
  const { props } = usePage();
  const email = props?.auth?.user?.email || "admin@example.com";
  const name = props?.auth?.user?.name || "Admin";

  const sections = [
    {
      title: "MAIN",
      items: [
        {
          key: "dashboard",
          label: "Dashboard",
          href: route("console.dashboard"),
          icon: (
            <Icon>
              <path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9.5z" />
            </Icon>
          ),
        },
      ],
    },
    {
      title: "AGENT",
      items: [
        {
          key: "agent_dashboard",
          label: "Agent dashboard",
          href: route("console.agent.dashboard"),
          icon: (
            <Icon>
              <path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4z" />
              <path d="M4 20a8 8 0 0 1 16 0" />
            </Icon>
          ),
        },
        {
          key: "recharges",
          label: "Recharges",
          href: route("console.agent.recharges"),
          icon: (
            <Icon>
              <path d="M12 1v22" />
              <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7H14a3.5 3.5 0 0 1 0 7H6" />
            </Icon>
          ),
        },
        {
          key: "withdrawals",
          label: "Withdrawals",
          href: route("console.agent.withdrawals"),
          icon: (
            <Icon>
              <path d="M12 5v14" />
              <path d="M7 10l5-5 5 5" />
            </Icon>
          ),
        },
        {
          key: "wallet",
          label: "Wallet",
          href: route("console.agent.wallet"),
          icon: (
            <Icon>
              <path d="M3 7h18v14H3z" />
              <path d="M3 11h18" />
              <path d="M16 15h2" />
            </Icon>
          ),
        },
      ],
    },
    {
      title: "ADMIN",
      items: [
        {
          key: "admin_overview",
          label: "Overview",
          href: route("console.admin.overview"),
          icon: (
            <Icon>
              <path d="M4 6h16" />
              <path d="M4 12h16" />
              <path d="M4 18h10" />
            </Icon>
          ),
        },
        {
          key: "admin_agents",
          label: "Agents",
          href: route("console.admin.agents"),
          icon: (
            <Icon>
              <path d="M16 11a4 4 0 1 0-4-4 4 4 0 0 0 4 4z" />
              <path d="M2 20a8 8 0 0 1 14-4" />
              <path d="M18 14a3 3 0 1 0 3 3 3 3 0 0 0-3-3z" />
            </Icon>
          ),
        },
        {
          key: "admin_topups",
          label: "Top-ups",
          href: route("console.admin.topups"),
          icon: (
            <Icon>
              <path d="M12 5v14" />
              <path d="M7 12h10" />
            </Icon>
          ),
        },
        {
          key: "admin_audit_log",
          label: "Audit log",
          href: route("console.admin.auditlog"),
          icon: (
            <Icon>
              <path d="M4 4h16v16H4z" />
              <path d="M8 8h8" />
              <path d="M8 12h8" />
              <path d="M8 16h5" />
            </Icon>
          ),
        },
      ],
    },
  ];

  return (
    <aside className="w-[280px] shrink-0 border-r border-white/10 bg-black/20">
      <div className="flex h-full flex-col px-4 py-5">
        <div className="mb-6">
          <div className="inline-flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-3 py-2">
            <div className="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-fuchsia-500/20 via-violet-500/10 to-amber-500/10 ring-1 ring-white/10">
              <span className="text-lg">🐱</span>
            </div>
            <div className="leading-tight">
              <div className="text-[12px] font-semibold tracking-[0.14em]">KITTY<span className="text-amber-300">PARTY</span></div>
              <div className="text-[11px] text-white/70">Offline Agent Console</div>
            </div>
          </div>
        </div>

        <nav className="space-y-6">
          {sections.map((sec) => (
            <div key={sec.title}>
              <div className="px-2 pb-2 text-[10px] font-semibold tracking-[0.32em] text-white/35">
                {sec.title}
              </div>
              <div className="space-y-1">
                {sec.items.map((it) => (
                  <NavItem
                    key={it.key}
                    href={it.href}
                    label={it.label}
                    active={active === it.key}
                    icon={it.icon}
                    badge={it.badge}
                  />
                ))}
              </div>
            </div>
          ))}
        </nav>

        <div className="mt-auto pt-6">
          <div className="rounded-2xl border border-white/10 bg-white/5 p-3">
            <div className="text-[12px] font-semibold">{name}</div>
            <div className="text-[11px] text-emerald-300/80">{email}</div>

            <div className="mt-3 flex items-center justify-between">
              <div className="text-[11px] text-white/40">Session</div>
              <Link
                href={route("logout")}
                method="post"
                as="button"
                className="rounded-xl border border-white/10 bg-black/20 px-3 py-1.5 text-[12px] text-white/80 hover:bg-white/10"
              >
                Logout
              </Link>
            </div>
          </div>

          <div className="mt-3 text-center text-[10px] text-white/25">
            GGH Software · Internal tool · 2025
          </div>
        </div>
      </div>
    </aside>
  );
}
