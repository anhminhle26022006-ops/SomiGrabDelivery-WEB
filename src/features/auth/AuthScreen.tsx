import { useState } from "react";
import {
  ArrowRight,
  CalendarDays,
  Check,
  Hash,
  Lock,
  CreditCard,
  Mail,
  Phone,
  ShieldCheck,
  Truck,
  UserRound,
} from "lucide-react";
import { LanguageSwitcher } from "../../shared/components/LanguageSwitcher";
import { useLanguage } from "../../shared/i18n";
import { LegalModal } from "./LegalModal";

export type AuthRole = "customer" | "shipper";
type AuthMode = "signin" | "signup";
type SignupStep = 1 | 2 | 3 | 4;

interface AuthScreenProps {
  onSuccess: (role: AuthRole) => void;
  onBack: () => void;
}

interface FormState {
  fullName: string;
  identifier: string;
  phone: string;
  email: string;
  password: string;
  confirmPassword: string;
  dateOfBirth: string;
  vehicleType: string;
  vehicleNumber: string;
  otp: string;
  nationalId: string;
  terms: boolean;
}

const initialForm: FormState = {
  fullName: "",
  identifier: "",
  phone: "",
  email: "",
  password: "",
  confirmPassword: "",
  dateOfBirth: "",
  vehicleType: "",
  vehicleNumber: "",
  otp: "",
  nationalId: "",
  terms: false,
};

export function AuthScreen({ onSuccess, onBack }: AuthScreenProps) {
  const { t } = useLanguage();
  const [role, setRole] = useState<AuthRole>("customer");
  const [mode, setMode] = useState<AuthMode>("signin");
  const [signupStep, setSignupStep] = useState<SignupStep>(1);
  const [form, setForm] = useState<FormState>(initialForm);
  const [error, setError] = useState("");
  const [vneidVerification, setVneidVerification] = useState<"not_started" | "pending">("not_started");
  const [legalDocument, setLegalDocument] = useState<"terms" | "privacy" | null>(null);

  const isSignup = mode === "signup";
  const update = (field: keyof FormState, value: string | boolean) => {
    setForm((current) => ({ ...current, [field]: value }));
    setError("");
  };

  const switchMode = (nextMode: AuthMode) => {
    setMode(nextMode);
    setSignupStep(1);
    setError("");
  };

  const submit = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError("");

    if (!isSignup) {
      onSuccess(role);
      return;
    }

    if (signupStep === 1) {
      const normalizedPhone = form.phone.replace(/\D/g, "");
      if (normalizedPhone.length < 9 || normalizedPhone.length > 11) {
        setError(t.auth.phoneInvalid);
        return;
      }
      setSignupStep(2);
      return;
    }

    if (signupStep === 2) {
      if (!/^\d{6}$/.test(form.otp)) {
        setError(t.auth.otpInvalid);
        return;
      }
      setSignupStep(3);
      return;
    }

    if (signupStep === 3) {
      if (!/^\d{12}$/.test(form.nationalId)) {
        setError(t.auth.nationalIdInvalid);
        return;
      }
      if (vneidVerification !== "pending") {
        setError(t.auth.vneidRequired);
        return;
      }
      setSignupStep(4);
      return;
    }

    if (form.password !== form.confirmPassword) {
      setError(t.auth.passwordMismatch);
      return;
    }
    if (form.password.length < 8) {
      setError(t.auth.passwordTooShort);
      return;
    }
    if (!form.terms) {
      setError(t.auth.termsRequired);
      return;
    }

    onSuccess(role);
  };

  const signupBack = () => {
    if (!isSignup || signupStep === 1) {
      onBack();
      return;
    }
    setSignupStep((step) => (step === 4 ? 3 : step === 3 ? 2 : 1));
    setError("");
  };

  return (
    <main className="min-h-screen overflow-x-hidden bg-[#f0f0f0] px-3 py-3 sm:px-5 sm:py-5 md:px-8 md:py-8">
      <div className="mx-auto grid min-h-[calc(100svh-1.5rem)] w-full max-w-5xl overflow-hidden rounded-[1.75rem] border border-white bg-white shadow-2xl shadow-[#14284b]/10 sm:min-h-[calc(100svh-2.5rem)] sm:rounded-[2.25rem] md:grid-cols-[.82fr_1.18fr] md:rounded-[3rem]">
        <section className="hidden bg-[#14284b] p-8 text-white md:flex md:flex-col md:justify-between lg:p-10">
          <button type="button" onClick={onBack} className="w-fit text-left text-xl tracking-[-.04em]">
            SOMI <span className="ml-1 text-[9px] tracking-[.2em] text-white/55">DELIVERY</span>
          </button>
          <div>
            <p className="text-xs uppercase tracking-[.22em] text-white/50">{t.auth.welcome}</p>
            <h1 className="mt-4 max-w-sm text-4xl font-semibold leading-[1.05] tracking-[-.04em]">{t.auth.title}</h1>
            <p className="mt-5 max-w-sm text-sm leading-6 text-white/65">{t.auth.desc}</p>
          </div>
          <p className="text-xs text-white/40">{t.auth.simple}</p>
        </section>

        <section className="min-w-0 overflow-y-auto p-5 sm:p-7 md:p-9 lg:p-10">
          <div className="flex items-center justify-between gap-3">
            <button type="button" onClick={signupBack} className="text-sm font-medium text-[#14284b] md:hidden">
              ← {t.auth.back}
            </button>
            <div className="ml-auto"><LanguageSwitcher /></div>
          </div>

          <div className="mx-auto mt-7 w-full max-w-lg sm:mt-8">
            <p className="text-xs font-semibold uppercase tracking-[.2em] text-[#14284b]/45">{isSignup ? t.auth.create : t.auth.signIn}</p>
            <h2 className="mt-2 text-3xl font-semibold tracking-[-.04em] text-[#14284b] sm:text-[2.1rem]">
              {isSignup ? (signupStep === 1 ? t.auth.start : signupStep === 2 ? t.auth.verifyPhone : signupStep === 3 ? t.auth.identityTitle : t.auth.completeProfile) : t.auth.welcomeTitle}
            </h2>
            <p className="mt-2 max-w-xl text-sm leading-6 text-slate-500">
              {isSignup ? (signupStep === 1 ? t.auth.phoneStep : signupStep === 2 ? t.auth.otpStep : signupStep === 3 ? t.auth.identityStep : t.auth.profileStep) : t.auth.choose}
            </p>

            <div className="mt-6 grid grid-cols-2 gap-1.5 rounded-2xl bg-[#f0f0f0] p-1">
              <button type="button" onClick={() => setRole("customer")} className={`flex min-h-11 items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-medium transition ${role === "customer" ? "bg-white text-[#14284b] shadow-sm" : "text-slate-500 hover:text-[#14284b]"}`}>
                <UserRound className="h-4 w-4" strokeWidth={1.7} />{t.auth.customer}
              </button>
              <button type="button" onClick={() => setRole("shipper")} className={`flex min-h-11 items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-medium transition ${role === "shipper" ? "bg-white text-[#14284b] shadow-sm" : "text-slate-500 hover:text-[#14284b]"}`}>
                <Truck className="h-4 w-4" strokeWidth={1.7} />{t.auth.shipper}
              </button>
            </div>

            {isSignup && (
              <div className="mt-5 flex items-center gap-2" aria-label={t.auth.progressLabel}>
                {[1, 2, 3, 4].map((step) => (
                  <div key={step} className="flex flex-1 items-center gap-2">
                    <div className={`h-1.5 flex-1 rounded-full ${signupStep >= step ? "bg-[#14284b]" : "bg-slate-200"}`} />
                    {step < 4 && <span className="hidden text-[10px] font-medium text-slate-400 sm:block">{step}</span>}
                  </div>
                ))}
              </div>
            )}

            <form onSubmit={submit} className="mt-6 space-y-4">
              {!isSignup && (
                <>
                  <Field label={t.auth.phoneOrEmail} icon={Phone} value={form.identifier} placeholder={t.auth.placeholderPhoneOrEmail} onChange={(value) => update("identifier", value)} required />
                  <PasswordField label={t.auth.password} value={form.password} placeholder="••••••••" onChange={(value) => update("password", value)} required />
                  <button type="button" className="text-left text-xs font-medium text-[#14284b]">{t.auth.forgotPassword}</button>
                </>
              )}

              {isSignup && signupStep === 1 && (
                <>
                  <Field label={t.auth.phone} icon={Phone} value={form.phone} placeholder={t.auth.placeholderPhone} type="tel" onChange={(value) => update("phone", value)} required />
                  <div className="flex items-start gap-3 rounded-2xl bg-[#f7f8fa] p-3.5 text-xs leading-5 text-slate-500">
                    <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0 text-[#14284b]/60" strokeWidth={1.7} />
                    <span>{t.auth.phoneSecurity}</span>
                  </div>
                </>
              )}

              {isSignup && signupStep === 2 && (
                <>
                  <div className="rounded-2xl border border-slate-200 bg-[#fbfcfd] p-4 text-sm text-[#14284b]">
                    <div className="flex items-center justify-between gap-3">
                      <span>{form.phone}</span>
                      <button type="button" onClick={() => setSignupStep(1)} className="text-xs font-medium underline underline-offset-4">{t.auth.changePhone}</button>
                    </div>
                  </div>
                  <Field label={t.auth.otp} icon={ShieldCheck} value={form.otp} placeholder="000000" inputMode="numeric" maxLength={6} onChange={(value) => update("otp", value.replace(/\D/g, "").slice(0, 6))} required />
                  <div className="flex items-center justify-between gap-3 text-xs text-slate-500">
                    <span>{t.auth.otpHint}</span>
                    <button type="button" className="shrink-0 font-medium text-[#14284b]">{t.auth.resendOtp}</button>
                  </div>
                </>
              )}

              {isSignup && signupStep === 3 && (
                <>
                  <div className="flex items-center gap-2 rounded-2xl bg-[#eef5ef] px-3.5 py-3 text-xs font-medium text-[#315a3b]"><Check className="h-4 w-4" strokeWidth={1.8} />{t.auth.phoneVerified}</div>
                  <div className="grid gap-4 sm:grid-cols-2">
                    <Field label={t.auth.fullName} icon={UserRound} value={form.fullName} placeholder={t.auth.placeholderName} onChange={(value) => update("fullName", value)} required />
                    <Field label={t.auth.dateOfBirth} icon={CalendarDays} value={form.dateOfBirth} type="date" onChange={(value) => update("dateOfBirth", value)} required />
                  </div>
                  <Field label={t.auth.nationalId} icon={CreditCard} value={form.nationalId} placeholder={t.auth.placeholderNationalId} inputMode="numeric" maxLength={12} onChange={(value) => update("nationalId", value.replace(/\D/g, "").slice(0, 12))} required />
                  <div className="rounded-2xl border border-slate-200 bg-[#fbfcfd] p-4">
                    <div className="flex items-start gap-3">
                      <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#eef2f8]"><ShieldCheck className="h-5 w-5 text-[#14284b]" strokeWidth={1.7} /></div>
                      <div className="min-w-0 flex-1">
                        <p className="text-sm font-semibold text-[#14284b]">{t.auth.vneidTitle}</p>
                        <p className="mt-1 text-xs leading-5 text-slate-500">{t.auth.vneidDesc}</p>
                      </div>
                    </div>
                    <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                      <div className={`flex items-center gap-2 text-xs font-medium ${vneidVerification === "pending" ? "text-[#315a3b]" : "text-slate-500"}`}>
                        <span className={`h-2 w-2 rounded-full ${vneidVerification === "pending" ? "bg-[#315a3b]" : "bg-slate-300"}`} />
                        {vneidVerification === "pending" ? t.auth.vneidPending : t.auth.vneidNotStarted}
                      </div>
                      <button type="button" onClick={() => setVneidVerification("pending")} className="rounded-full border border-[#14284b]/15 bg-white px-4 py-2 text-xs font-semibold text-[#14284b] shadow-sm hover:bg-[#f7f8fa]">{t.auth.verifyVneid}</button>
                    </div>
                    <p className="mt-3 text-[10px] leading-4 text-slate-400">{t.auth.vneidProviderNote}</p>
                  </div>
                </>
              )}

              {isSignup && signupStep === 4 && (
                <>
                  <div className="flex items-center gap-2 rounded-2xl bg-[#eef5ef] px-3.5 py-3 text-xs font-medium text-[#315a3b]"><Check className="h-4 w-4" strokeWidth={1.8} />{t.auth.identityReady}</div>
                  <Field label={t.auth.email} icon={Mail} value={form.email} placeholder={t.auth.placeholderEmail} type="email" onChange={(value) => update("email", value)} required />
                  {role === "shipper" && (
                    <div className="grid gap-4 sm:grid-cols-2">
                      <SelectField label={t.auth.vehicleType} icon={Truck} value={form.vehicleType} onChange={(value) => update("vehicleType", value)} options={[t.auth.motorbike, t.auth.car]} />
                      <Field label={t.auth.vehicleNumber} icon={Hash} value={form.vehicleNumber} placeholder={t.auth.placeholderVehicle} onChange={(value) => update("vehicleNumber", value)} required />
                    </div>
                  )}
                  <div className="grid gap-4 sm:grid-cols-2">
                    <PasswordField label={t.auth.password} value={form.password} placeholder="••••••••" onChange={(value) => update("password", value)} required />
                    <PasswordField label={t.auth.confirmPassword} value={form.confirmPassword} placeholder="••••••••" onChange={(value) => update("confirmPassword", value)} required />
                  </div>
                  <p className="text-[11px] text-slate-400">{t.auth.passwordHint}</p>
                  <div className="flex items-start gap-3 rounded-2xl bg-[#f7f8fa] p-3.5 text-xs leading-5 text-slate-500">
                    <input type="checkbox" checked={form.terms} onChange={(event) => update("terms", event.target.checked)} className="mt-0.5 h-4 w-4 shrink-0 accent-[#14284b]" />
                    <span>
                      {t.auth.termsIntro}{" "}
                      <button type="button" onClick={() => setLegalDocument("terms")} className="font-semibold text-[#14284b] underline underline-offset-2">{t.auth.termsLink}</button>{" "}
                      {t.auth.and}{" "}
                      <button type="button" onClick={() => setLegalDocument("privacy")} className="font-semibold text-[#14284b] underline underline-offset-2">{t.auth.privacyLink}</button>.
                    </span>
                  </div>
                </>
              )}

              {error && <p className="rounded-xl bg-red-50 px-3.5 py-2.5 text-xs text-red-600" role="alert">{error}</p>}

              <button type="submit" className="flex min-h-12 w-full items-center justify-center gap-2 rounded-full bg-[#14284b] px-5 py-3 text-sm font-semibold text-white shadow-lg transition hover:bg-[#10223f]">
                {isSignup ? (signupStep === 1 ? t.auth.sendOtp : signupStep === 2 ? t.auth.verifyAndContinue : signupStep === 3 ? t.auth.verifyIdentity : t.auth.submitCreate) : t.auth.signIn}
                <ArrowRight className="h-4 w-4" strokeWidth={1.7} />
              </button>
              {isSignup && <div className="flex items-center justify-center gap-2 text-[11px] text-slate-400"><ShieldCheck className="h-3.5 w-3.5" strokeWidth={1.7} />{t.auth.secureNote}</div>}
            </form>

            <div className="mt-6 pb-2 text-center text-sm text-slate-500">
              {isSignup ? t.auth.hasAccount : t.auth.noAccount}{" "}
              <button type="button" onClick={() => switchMode(isSignup ? "signin" : "signup")} className="font-semibold text-[#14284b] underline underline-offset-4">
                {isSignup ? t.auth.signIn : t.auth.create}
              </button>
            </div>
          </div>
        </section>
      </div>
      {legalDocument && <LegalModal document={legalDocument} onClose={() => setLegalDocument(null)} />}
    </main>
  );
}

function Field({ label, icon: Icon, value, placeholder, type = "text", inputMode, maxLength, onChange, required }: { label: string; icon: typeof UserRound; value: string; placeholder?: string; type?: string; inputMode?: "numeric" | "text" | "email" | "tel"; maxLength?: number; onChange: (value: string) => void; required?: boolean }) {
  return (
    <label className="block min-w-0">
      <span className="text-xs font-medium text-slate-500">{label}</span>
      <div className="mt-2 flex min-h-12 items-center gap-2 rounded-xl border border-slate-200 px-3 transition focus-within:border-[#14284b]/35 focus-within:ring-2 focus-within:ring-[#14284b]/5">
        <Icon className="h-4 w-4 shrink-0 text-slate-400" strokeWidth={1.7} />
        <input required={required} type={type} inputMode={inputMode} maxLength={maxLength} value={value} onChange={(event) => onChange(event.target.value)} className="min-w-0 w-full bg-transparent text-sm outline-none" placeholder={placeholder} />
      </div>
    </label>
  );
}

function PasswordField({ label, value, placeholder, onChange, required }: { label: string; value: string; placeholder: string; onChange: (value: string) => void; required?: boolean }) {
  return <Field label={label} icon={Lock} value={value} placeholder={placeholder} type="password" onChange={onChange} required={required} />;
}

function SelectField({ label, icon: Icon, value, options, onChange }: { label: string; icon: typeof Truck; value: string; options: string[]; onChange: (value: string) => void }) {
  return (
    <label className="block min-w-0">
      <span className="text-xs font-medium text-slate-500">{label}</span>
      <div className="mt-2 flex min-h-12 items-center gap-2 rounded-xl border border-slate-200 px-3 transition focus-within:border-[#14284b]/35 focus-within:ring-2 focus-within:ring-[#14284b]/5">
        <Icon className="h-4 w-4 shrink-0 text-slate-400" strokeWidth={1.7} />
        <select required value={value} onChange={(event) => onChange(event.target.value)} className="min-w-0 w-full bg-transparent text-sm outline-none">
          <option value="">—</option>
          {options.map((option) => <option key={option} value={option}>{option}</option>)}
        </select>
      </div>
    </label>
  );
}
