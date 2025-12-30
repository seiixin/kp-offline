import React from "react";
import { Head, Link, useForm } from "@inertiajs/react";
import InputError from "@/Components/InputError";

function KittyPartyBadge() {
  return (
    <div className="inline-flex items-center gap-3 rounded-2xl border border-white/10 bg-black/40 px-4 py-2 shadow-[0_10px_30px_rgba(0,0,0,.35)] backdrop-blur">
      <span className="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-fuchsia-500 via-pink-500 to-amber-400 shadow-[0_0_0_1px_rgba(255,255,255,.25)]">
        <span className="text-[12px] font-black tracking-wide text-black">KP</span>
      </span>

      <div className="leading-tight">
        <div className="text-[12px] font-extrabold tracking-[0.25em]">KITTY PARTY</div>
        <div className="text-[11px] text-white/70">Offline Agent Console</div>
      </div>
    </div>
  );
}

export default function Login({ status, canResetPassword }) {
  const { data, setData, post, processing, errors, reset } = useForm({
    email: "",
    password: "",
    remember: false,
  });

  const submit = (e) => {
    e.preventDefault();
    post(route("login"), {
      onFinish: () => reset("password"),
    });
  };

  return (
    <>
      <Head title="Log in" />

      <div className="min-h-screen text-white">
        {/* Background */}
        <div className="absolute inset-0 -z-10 bg-[#050816]" />
        <div
          className="absolute inset-0 -z-10 opacity-80"
          style={{
            background:
              "radial-gradient(1200px 600px at 85% -10%, rgba(168, 85, 247, .22), transparent 55%), radial-gradient(900px 520px at 10% 115%, rgba(59, 130, 246, .14), transparent 60%)",
          }}
        />
        <div className="absolute inset-0 -z-10 bg-gradient-to-b from-black/0 via-black/10 to-black/30" />

        <div className="mx-auto flex min-h-screen w-full max-w-[1100px] items-center justify-center px-6 py-12">
          <div className="w-full max-w-[560px]">
            <div className="flex flex-col items-center text-center">
              <KittyPartyBadge />

              <div className="mt-6 text-balance text-[13px] leading-relaxed text-white/70">
                Sign in to manage offline coin recharges, withdrawals, and agents inside the KittyParty backoffice.
              </div>
            </div>

            <div className="mt-8 rounded-2xl border border-white/10 bg-white/[0.04] p-7 shadow-[0_30px_80px_rgba(0,0,0,.45)] backdrop-blur">
              {status && (
                <div className="mb-4 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-[12px] text-emerald-100">
                  {status}
                </div>
              )}

              <form onSubmit={submit} className="space-y-5">
                <div>
                  <label htmlFor="email" className="block text-[12px] font-medium text-white/55">
                    Email
                  </label>
                  <input
                    id="email"
                    type="email"
                    name="email"
                    value={data.email}
                    autoComplete="username"
                    autoFocus
                    onChange={(e) => setData("email", e.target.value)}
                    className="mt-2 w-full rounded-xl bg-white px-4 py-3 text-[14px] text-slate-900 shadow-sm ring-1 ring-black/10 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-fuchsia-400/60"
                  />
                  <InputError message={errors.email} className="mt-2 text-[12px]" />
                </div>

                <div>
                  <label htmlFor="password" className="block text-[12px] font-medium text-white/55">
                    Password
                  </label>
                  <input
                    id="password"
                    type="password"
                    name="password"
                    value={data.password}
                    autoComplete="current-password"
                    onChange={(e) => setData("password", e.target.value)}
                    className="mt-2 w-full rounded-xl bg-white px-4 py-3 text-[14px] text-slate-900 shadow-sm ring-1 ring-black/10 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-fuchsia-400/60"
                  />
                  <InputError message={errors.password} className="mt-2 text-[12px]" />
                </div>

                <div className="flex items-center justify-between gap-4 pt-1">
                  <label className="inline-flex items-center gap-2 text-[12px] text-white/55">
                    <input
                      type="checkbox"
                      name="remember"
                      checked={data.remember}
                      onChange={(e) => setData("remember", e.target.checked)}
                      className="h-4 w-4 rounded border-white/20 bg-white/10 text-fuchsia-400 focus:ring-fuchsia-400/40"
                    />
                    Remember me
                  </label>

                  {canResetPassword && (
                    <Link
                      href={route("password.request")}
                      className="text-[12px] text-white/45 underline-offset-4 hover:text-white/70 hover:underline"
                    >
                      Forgot your password?
                    </Link>
                  )}
                </div>

                <div className="flex items-center justify-end pt-1">
                  <button
                    type="submit"
                    disabled={processing}
                    className="rounded-xl bg-white/10 px-6 py-2.5 text-[12px] font-semibold tracking-[0.15em] text-white shadow-sm ring-1 ring-white/10 hover:bg-white/15 disabled:opacity-60"
                  >
                    LOG IN
                  </button>
                </div>
              </form>
            </div>

            <div className="mt-6 text-center text-[11px] text-white/25">
              GGH Software • Internal tool • 2025
            </div>
          </div>
        </div>
      </div>
    </>
  );
}
