import { Check, ChevronDown, Globe2 } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { useLanguage, type Language } from "../i18n";

const options: Array<{ value: Language; label: string }> = [
  { value: "vi", label: "Tiếng Việt" },
  { value: "en", label: "English" },
];

export function LanguageSwitcher() {
  const { lang, setLang } = useLanguage();
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const close = (event: MouseEvent) => {
      if (!ref.current?.contains(event.target as Node)) setOpen(false);
    };
    document.addEventListener("mousedown", close);
    return () => document.removeEventListener("mousedown", close);
  }, [open]);

  const current = options.find((option) => option.value === lang) ?? options[0];

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        onClick={() => setOpen((value) => !value)}
        aria-expanded={open}
        aria-haspopup="menu"
        className="group flex h-10 items-center gap-2 rounded-full border border-slate-200/80 bg-white/70 px-3 backdrop-blur-md text-[#14284b] shadow-sm transition hover:border-slate-300 hover:bg-white"
      >
        <Globe2 className="h-4 w-4 text-[#14284b]/60 transition group-hover:text-[#14284b]" strokeWidth={1.7} />
        <span className="text-[11px] font-medium uppercase tracking-[.08em]">{current.value}</span>
        <ChevronDown className={`h-3.5 w-3.5 text-[#14284b]/45 transition ${open ? "rotate-180" : ""}`} strokeWidth={1.7} />
      </button>

      {open && (
        <div
          role="menu"
          className="absolute right-0 top-[calc(100%+8px)] z-[70] w-40 overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-1.5 shadow-[0_18px_45px_rgba(20,40,75,.14)]"
        >
          {options.map((option) => (
            <button
              key={option.value}
              type="button"
              role="menuitem"
              onClick={() => {
                setLang(option.value);
                setOpen(false);
              }}
              className={`flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-left text-xs transition ${
                lang === option.value
                  ? "bg-[#f0f3f7] text-[#14284b]"
                  : "text-slate-600 hover:bg-[#f7f8fa] hover:text-[#14284b]"
              }`}
            >
              <span>{option.label}</span>
              {lang === option.value && <Check className="h-3.5 w-3.5" strokeWidth={1.8} />}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
