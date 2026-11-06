/**
 * ============================================================================
 * NIK SMART COUNT — Chart of Accounts (COA)
 * ============================================================================
 *
 * A standard, three-layer chart of accounts tailored for a construction /
 * real-estate development business (multi-storey apartment projects).
 *
 * Layers:
 *   Group (e.g. 5000)  ->  Main account (e.g. 5100)  ->  Sub account (Phase 2+,
 *   user-creatable, e.g. 5110 "Rebar — Supplier A") via `parentCode`.
 *
 * Design principles:
 *  - Users never see codes. The UI keeps working with «مصارف / پرداخت‌ها /
 *    فروشات / سرمایه‌گذاری» — the journal engine maps them to accounts
 *    behind the scenes (see `src/utils/journalEngine.ts`).
 *  - 5xxx accounts = DIRECT PROJECT costs only (they conceptually feed the
 *    Work-in-Progress asset 1500; the WIP/COGS reclass lands in Phase 3).
 *  - 6xxx accounts = company-level operating/admin expenses (NOT project
 *    costs) so project P&L stays clean.
 *  - Multi-currency: postings keep the original currency plus USD/AFN
 *    normalization at journal-line level (the app's canonical currencies).
 * ============================================================================
 */

export type AccountType = 'asset' | 'liability' | 'equity' | 'revenue' | 'expense';
export type NormalBalance = 'debit' | 'credit';

export interface Account {
  /** 4-digit account code, e.g. '5100' */
  code: string;
  type: AccountType;
  /** The side on which the account normally increases */
  normalBalance: NormalBalance;
  /** Parent account code for the 3-layer hierarchy (undefined for groups) */
  parentCode?: string;
  nameEn: string;
  /** Dari */
  nameFa: string;
  /** Pashto */
  namePs: string;
  /**
   * Header accounts are grouping nodes only — journal lines must never post
   * directly to them (they roll up from their children).
   */
  isHeader?: boolean;
  /**
   * System accounts are part of the standard chart and cannot be deleted by
   * users (Phase 2+ when a UI for custom sub-accounts exists).
   */
  system?: boolean;
  /** Which implementation phase the account becomes actively posted to */
  activePhase?: 1 | 2 | 3;
  description?: string;
}

/* ------------------------------------------------------------------ */
/* The Chart                                                           */
/* ------------------------------------------------------------------ */

export const CHART_OF_ACCOUNTS: Account[] = [
  {"code":"1410","parentCode":"1400","type":"asset","normalBalance":"debit","nameEn":"Steel and binding wire","nameFa":"سیخ گول و سیم بندش","namePs":"سیخ ګول او تړلو سیم","system":true,"activePhase":3},
  {"code":"1420","parentCode":"1400","type":"asset","normalBalance":"debit","nameEn":"Cement and concrete ingredients","nameFa":"سمنت و مواد کانکریت","namePs":"سمنټ او د کانکریټ توکي","system":true,"activePhase":3},
  {"code":"1430","parentCode":"1400","type":"asset","normalBalance":"debit","nameEn":"Bricks and blocks","nameFa":"خشت و بلاک","namePs":"خښتې او بلاکونه","system":true,"activePhase":3},
  {"code":"1440","parentCode":"1400","type":"asset","normalBalance":"debit","nameEn":"Timber and formwork stock","nameFa":"چوب و وسایل قالب","namePs":"لرګي او قالب وسایل","system":true,"activePhase":3},
  {"code":"1450","parentCode":"1400","type":"asset","normalBalance":"debit","nameEn":"Electrical stock","nameFa":"مواد برق","namePs":"د برېښنا توکي","system":true,"activePhase":3},
  {"code":"1460","parentCode":"1400","type":"asset","normalBalance":"debit","nameEn":"Plumbing and sanitary stock","nameFa":"نل و وسایل تشناب","namePs":"نلونه او د تشناب وسایل","system":true,"activePhase":3},
  {"code":"1470","parentCode":"1400","type":"asset","normalBalance":"debit","nameEn":"Doors, windows and glass","nameFa":"دروازه، کلکین و شیشه","namePs":"دروازې، کړکۍ او ښیښه","system":true,"activePhase":3},
  {"code":"1480","parentCode":"1400","type":"asset","normalBalance":"debit","nameEn":"Tiles, paint and decoration stock","nameFa":"کاشی، رنگ و مواد دیکور","namePs":"کاشي، رنګ او سینګار توکي","system":true,"activePhase":3},
  {"code":"1490","parentCode":"1400","type":"asset","normalBalance":"debit","nameEn":"Mechanical and safety stock","nameFa":"مواد میخانیکی و ایمنی","namePs":"میخانیکي او خوندیتوب توکي","system":true,"activePhase":3},
  {"code":"5110","parentCode":"5100","type":"expense","normalBalance":"debit","nameEn":"Binding wire and steel accessories","nameFa":"سیم بندش و وسایل سیخ گول","namePs":"د تړلو سیم او سیخ وسایل","system":true,"activePhase":1},
  {"code":"5120","parentCode":"5100","type":"expense","normalBalance":"debit","nameEn":"Welding and steel fabrication","nameFa":"ولدنگ و ساخت وسایل فلزی","namePs":"ویلډنګ او فلزي جوړول","system":true,"activePhase":1},
  {"code":"5210","parentCode":"5200","type":"expense","normalBalance":"debit","nameEn":"Concrete admixtures and curing","nameFa":"مواد تقویتی کانکریت و مراقبت آن","namePs":"د کانکریټ اضافي توکي او پالنه","system":true,"activePhase":1},
  {"code":"5610","parentCode":"5600","type":"expense","normalBalance":"debit","nameEn":"Painting labor","nameFa":"مزد رنگمالی","namePs":"د رنګمالۍ مزدوري","system":true,"activePhase":1},
  {"code":"5620","parentCode":"5600","type":"expense","normalBalance":"debit","nameEn":"Decoration and finishing labor","nameFa":"مزد دیکور و کار تکمیلی","namePs":"د سینګار او بشپړولو مزدوري","system":true,"activePhase":1},
  {"code":"5913","parentCode":"5910","type":"expense","normalBalance":"debit","nameEn":"Scaffolding fittings and safety nets","nameFa":"وسایل داربست و جال ایمنی","namePs":"داربست وسایل او خوندیتوب جال","system":true,"activePhase":1},
  {"code":"5924","parentCode":"5920","type":"expense","normalBalance":"debit","nameEn":"Generators, transformers and solar","nameFa":"جنراتور، ترانسفارمر و سولر","namePs":"جنراتور، ټرانسفارمر او لمریزه برېښنا","system":true,"activePhase":1},
  {"code":"5934","parentCode":"5930","type":"expense","normalBalance":"debit","nameEn":"Water tanks, pumps and heaters","nameFa":"تانکر آب، پمپ و آب‌گرم‌کن","namePs":"د اوبو ټانک، پمپ او ګرمونکی","system":true,"activePhase":1},
  {"code":"5965","parentCode":"5960","type":"expense","normalBalance":"debit","nameEn":"Excavation and soil removal","nameFa":"کندن‌کاری و انتقال خاک","namePs":"کیندنه او د خاورې لېږد","system":true,"activePhase":1},
  {"code":"5966","parentCode":"5960","type":"expense","normalBalance":"debit","nameEn":"Shoring and ground stabilization","nameFa":"استحکام دیوار و زمین","namePs":"د دیوال او ځمکې ټینګول","system":true,"activePhase":1},
  {"code":"5967","parentCode":"5960","type":"expense","normalBalance":"debit","nameEn":"Fire protection and alarms","nameFa":"وسایل ضد حریق و زنگ خطر","namePs":"د اور ساتنې وسایل او زنګ","system":true,"activePhase":1},
  {"code":"5971","parentCode":"5970","type":"expense","normalBalance":"debit","nameEn":"Engineering and surveys","nameFa":"انجنیری و سروی","namePs":"انجنیري او سروې","system":true,"activePhase":1},
  {"code":"5972","parentCode":"5970","type":"expense","normalBalance":"debit","nameEn":"Permits and inspection fees","nameFa":"جواز و فیس بررسی","namePs":"جواز او د پلټنې فیس","system":true,"activePhase":1},
  {"code":"5981","parentCode":"5980","type":"expense","normalBalance":"debit","nameEn":"Site safety and protective clothing","nameFa":"ایمنی ساحه و لباس محافظتی","namePs":"د ساحې خوندیتوب او ساتندوی کالي","system":true,"activePhase":1},
  {"code":"5982","parentCode":"5980","type":"expense","normalBalance":"debit","nameEn":"Site cleaning and waste removal","nameFa":"پاک‌کاری ساحه و انتقال زباله","namePs":"د ساحې پاکول او کثافات وړل","system":true,"activePhase":1},
  {"code":"5983","parentCode":"5980","type":"expense","normalBalance":"debit","nameEn":"Material testing and quality control","nameFa":"تست مواد و کنترول کیفیت","namePs":"د توکو ازموینه او کیفیت کنټرول","system":true,"activePhase":1},
  {code:'1360',parentCode:'1000',type:'asset',normalBalance:'debit',nameEn:'Inter-project Clearing',nameFa:'حساب تصفیهٔ بین پروژه‌ها',namePs:'د پروژو ترمنځ د تصفیې حساب',system:true,activePhase:1},
  {code:'1310',parentCode:'1000',type:'asset',normalBalance:'debit',nameEn:'Employee Advances',nameFa:'پیشکی کارمندان',namePs:'د کارمندانو مخکې ورکړې',system:true,activePhase:1},
  /* ============================ 1000 ASSETS ============================ */
  { code: '1000', type: 'asset', normalBalance: 'debit', nameEn: 'Assets', nameFa: 'دارایی‌ها', namePs: 'شتوالۍ', isHeader: true, system: true },
  { code: '1100', type: 'asset', normalBalance: 'debit', parentCode: '1000', nameEn: 'Cash & Bank', nameFa: 'نقد و بانک', namePs: 'نقد او بانک', isHeader: true, system: true },
  { code: '1110', type: 'asset', normalBalance: 'debit', parentCode: '1100', nameEn: 'Cash (AFN)', nameFa: 'صندوق افغانی', namePs: 'د افغانیو صندوق', system: true, activePhase: 1 },
  { code: '1120', type: 'asset', normalBalance: 'debit', parentCode: '1100', nameEn: 'Cash (USD)', nameFa: 'صندوق دالر', namePs: 'د ډالرو صندوق', system: true, activePhase: 1 },
  { code: '1130', type: 'asset', normalBalance: 'debit', parentCode: '1100', nameEn: 'Bank Account', nameFa: 'حساب بانکی', namePs: 'بانکي حساب', system: true, activePhase: 1 },
  { code: '1140', type: 'asset', normalBalance: 'debit', parentCode: '1100', nameEn: 'Hawala / Sarafi Balance', nameFa: 'حساب صرافی و حواله', namePs: 'د صرافۍ حساب (حواله)', system: true, activePhase: 1 },
  { code: '1200', type: 'asset', normalBalance: 'debit', parentCode: '1000', nameEn: 'Apartment Receivables', nameFa: 'باقی پول خریداران اپارتمان', namePs: 'د اپارتمانونو له پیرودونکو ترلاسه کیدونکي', system: true, activePhase: 1,
    description: 'Amounts still owed by unit buyers (sale price minus receipts).' },
  { code: '1300', type: 'asset', normalBalance: 'debit', parentCode: '1000', nameEn: 'Advances to Suppliers', nameFa: 'پیش‌پرداخت به فروشنده‌ها', namePs: 'پلورونکو ته مخکینۍ تادیه', system: true, activePhase: 3 },
  { code: '1400', type: 'asset', normalBalance: 'debit', parentCode: '1000', nameEn: 'Material Inventory', nameFa: 'موجودی مواد', namePs: 'د موادو زیرمه', system: true, activePhase: 3 },
  { code: '1500', type: 'asset', normalBalance: 'debit', parentCode: '1000', nameEn: 'Construction Work in Progress (WIP)', nameFa: 'کار ساختمانی در جریان', namePs: 'روانه پروژه (WIP)', system: true, activePhase: 3,
    description: 'Capitalized direct project costs per project; relieved to COGS on unit sale (Phase 3).' },
  { code: '1600', type: 'asset', normalBalance: 'debit', parentCode: '1000', nameEn: 'Deposits', nameFa: 'ودیعه‌ها و امانت‌های داده‌شده', namePs: 'امانتونه', system: true, activePhase: 2 },
  { code: '1800', type: 'asset', normalBalance: 'debit', parentCode: '1000', nameEn: 'Fixed Assets', nameFa: 'دارایی‌های ثابت (مکینت، داربست، وسایط)', namePs: 'ثابتې شتوالۍ', system: true, activePhase: 2 },

  /* ========================= 2000 LIABILITIES ========================= */
  { code: '2000', type: 'liability', normalBalance: 'credit', nameEn: 'Liabilities', nameFa: 'قرضداری‌ها', namePs: 'پورونه', isHeader: true, system: true },
  { code: '2100', type: 'liability', normalBalance: 'credit', parentCode: '2000', nameEn: 'Accounts Payable', nameFa: 'قرضهای قابل پرداخت', namePs: 'تادیه وړ حسابونه', isHeader: true, system: true },
  { code: '2200', type: 'liability', normalBalance: 'credit', parentCode: '2100', nameEn: 'Supplier Payables', nameFa: 'قرضداری به فروشنده‌ها', namePs: 'پلورونکو ته پور', system: true, activePhase: 1 },
  { code: '2250', type: 'liability', normalBalance: 'credit', parentCode: '2100', nameEn: 'Contractor Payables', nameFa: 'قرضداری به قراردادی‌ها', namePs: 'قراردادکارانو ته پور', system: true, activePhase: 1 },
  { code: '2300', type: 'liability', normalBalance: 'credit', parentCode: '2000', nameEn: 'Customer Advances', nameFa: 'پیشکی خریداران (پیش از قرارداد فروش)', namePs: 'له پیرودونکو څخه مخکینۍ ترلاسه کول', system: true, activePhase: 1,
    description: 'Receipts from buyers of RESERVED (not yet formally sold) units.' },
  { code: '2400', type: 'liability', normalBalance: 'credit', parentCode: '2000', nameEn: 'Retention Payable', nameFa: 'تضمین قابل پرداخت به قراردادی', namePs: 'قراردادکار ته ساتل شوی امانت', system: true, activePhase: 2,
    description: 'Percentage withheld from contractor progress payments (retentionPercentage).' },
  { code: '2500', type: 'liability', normalBalance: 'credit', parentCode: '2000', nameEn: 'Loans Payable', nameFa: 'قرض‌های قابل پرداخت', namePs: 'پورونه او قرضې', system: true, activePhase: 2 },
  { code: '2600', type: 'liability', normalBalance: 'credit', parentCode: '2000', nameEn: 'Taxes Payable', nameFa: 'مالیهٔ قابل پرداخت', namePs: 'تادیه وړ مالیه', system: true, activePhase: 2 },
  { code: '2900', type: 'liability', normalBalance: 'credit', parentCode: '2000', nameEn: 'Other Liabilities', nameFa: 'سایر قرضداری‌ها (شامل پرداخت از جیب شخصی)', namePs: 'نور پورونه', system: true, activePhase: 1 },

  /* ============================ 3000 EQUITY =========================== */
  { code: '3000', type: 'equity', normalBalance: 'credit', nameEn: 'Equity', nameFa: 'سرمایه', namePs: 'پانګه', isHeader: true, system: true },
  { code: '3100', type: 'equity', normalBalance: 'credit', parentCode: '3000', nameEn: "Partners' Capital (Initial)", nameFa: 'سرمایه اولی شرکا', namePs: 'د شریکانو لومړنۍ پانګه', system: true, activePhase: 1 },
  { code: '3200', type: 'equity', normalBalance: 'credit', parentCode: '3000', nameEn: 'Additional Investments', nameFa: 'سرمایه‌گذاری‌های اضافی شرکا', namePs: 'د شریکانو اضافي پانګه اچونه', system: true, activePhase: 1 },
  { code: '3300', type: 'equity', normalBalance: 'debit', parentCode: '3000', nameEn: 'Partner Drawings', nameFa: 'برداشت شرکا', namePs: 'د شریکانو را ایستل', system: true, activePhase: 2,
    description: 'Contra-equity: money partners take OUT of the business.' },
  { code: '3400', type: 'equity', normalBalance: 'credit', parentCode: '3000', nameEn: 'Retained Earnings', nameFa: 'مفاد و ضرر جمع‌شده', namePs: 'ذخیره شوې ګټه او زیان', system: true, activePhase: 2 },

  /* ============================ 4000 REVENUE ========================== */
  { code: '4000', type: 'revenue', normalBalance: 'credit', nameEn: 'Revenue', nameFa: 'عواید', namePs: 'عاید', isHeader: true, system: true },
  { code: '4100', type: 'revenue', normalBalance: 'credit', parentCode: '4000', nameEn: 'Apartment & Unit Sales', nameFa: 'فروش اپارتمان و واحدها', namePs: 'د اپارتمانونو او واحدونو پلور', system: true, activePhase: 1 },
  { code: '4200', type: 'revenue', normalBalance: 'credit', parentCode: '4000', nameEn: 'Rental Income', nameFa: 'عاید کرایهٔ دوکان‌ها و سایر ملکیت‌ها', namePs: 'د کرایې عاید', system: true, activePhase: 2 },
  { code: '4300', type: 'revenue', normalBalance: 'credit', parentCode: '4000', nameEn: 'Other Project Revenue', nameFa: 'سایر عواید پروژه', namePs: 'د پروژې نور عایدونه', system: true, activePhase: 1 },

  /* ================ 5000 PROJECT / CONSTRUCTION COSTS ================= */
  { code: '5000', type: 'expense', normalBalance: 'debit', nameEn: 'Construction / Project Costs', nameFa: 'مصارف اعمار پروژه', namePs: 'د پروژې د جوړښت لګښتونه', isHeader: true, system: true,
    description: 'DIRECT project costs only. Conceptually these feed WIP (1500); WIP/COGS reclass arrives in Phase 3.' },
  { code: '5050', type: 'expense', normalBalance: 'debit', parentCode: '5000', nameEn: 'Cost of Units Sold (COGS)', nameFa: 'مصرف واحدهای فروخته‌شده', namePs: 'د پلورل شویو واحدونو لګښت', system: true, activePhase: 3 },
  { code: '5100', type: 'expense', normalBalance: 'debit', parentCode: '5000', nameEn: 'Steel & Rebar', nameFa: 'سیخ‌گول', namePs: 'اوسپنه او سیخ ګول', system: true, activePhase: 1 },
  { code: '5200', type: 'expense', normalBalance: 'debit', parentCode: '5000', nameEn: 'Concrete & Pumping', nameFa: 'کانکریت و پمپ', namePs: 'کانکریت او پمپ', system: true, activePhase: 1 },
  { code: '5300', type: 'expense', normalBalance: 'debit', parentCode: '5000', nameEn: 'Cement', nameFa: 'سمنت', namePs: 'سمنټ', system: true, activePhase: 1 },
  { code: '5400', type: 'expense', normalBalance: 'debit', parentCode: '5000', nameEn: 'Sand & Gravel', nameFa: 'ریگ و جغل', namePs: 'شګه او ریګ', system: true, activePhase: 1 },
  { code: '5500', type: 'expense', normalBalance: 'debit', parentCode: '5000', nameEn: 'Masonry (Blocks & Bricks)', nameFa: 'بلاک، خشت و بنایات', namePs: 'بلاک، خشتې او بنایات', system: true, activePhase: 1 },
  { code: '5600', type: 'expense', normalBalance: 'debit', parentCode: '5000', nameEn: 'Labour & Wages', nameFa: 'اجرت و معاش کارگران', namePs: 'مزد او کارګران', system: true, activePhase: 1 },
  { code: '5700', type: 'expense', normalBalance: 'debit', parentCode: '5000', nameEn: 'Machinery & Equipment Rental', nameFa: 'کرایه ماشین‌آلات و تجهیزات', namePs: 'د ماشینونو او تجهیزاتو کرایه', system: true, activePhase: 1 },
  { code: '5800', type: 'expense', normalBalance: 'debit', parentCode: '5000', nameEn: 'Transportation & Logistics', nameFa: 'ترانسپورت و حمل‌ونقل', namePs: 'ترانسپورت او لېږد', system: true, activePhase: 1 },
  { code: '5900', type: 'expense', normalBalance: 'debit', parentCode: '5000', nameEn: 'Site Utilities & Fuel', nameFa: 'آب‌وبرق و تیل ساحه', namePs: 'د ساحې اوبه، برق او تیل', system: true, activePhase: 1 },
  { code: '5910', type: 'expense', normalBalance: 'debit', parentCode: '5000', nameEn: 'Formwork & Scaffolding', nameFa: 'قالب‌بندی و داربست', namePs: 'قالب بندي او داربست', system: true, activePhase: 1 },
  { code: '5920', type: 'expense', normalBalance: 'debit', parentCode: '5000', nameEn: 'Electrical Works', nameFa: 'برق‌کاری و تأسیسات برقی', namePs: 'د برېښنا کارونه', system: true, activePhase: 1 },
  { code: '5930', type: 'expense', normalBalance: 'debit', parentCode: '5000', nameEn: 'Plumbing & Sanitary', nameFa: 'نل‌کاری و تأسیسات صحی', namePs: 'د لولو او صحي کارونه', system: true, activePhase: 1 },
  { code: '5940', type: 'expense', normalBalance: 'debit', parentCode: '5000', nameEn: 'Carpentry, Doors & Windows', nameFa: 'نجاری، دروازه و کلکین', namePs: 'نجاري، دروازې او ورکونه', system: true, activePhase: 1 },
  { code: '5950', type: 'expense', normalBalance: 'debit', parentCode: '5000', nameEn: 'Tiles, Paint & Finishing', nameFa: 'کاشی، رنگمالی و تکمیل‌کاری', namePs: 'کاشي، رنګ او بشپړونکي کار', system: true, activePhase: 1 },
  { code: '5960', type: 'expense', normalBalance: 'debit', parentCode: '5000', nameEn: 'Elevator, Excavation & Shoring', nameFa: 'لیفت، کندن‌کاری و تحکیمات', namePs: 'لفټ، کیندنه او تکیه ګاه', system: true, activePhase: 1 },
  { code: '5970', type: 'expense', normalBalance: 'debit', parentCode: '5000', nameEn: 'Permits, Fees & Engineering', nameFa: 'جواز، فیس و انجنیری', namePs: 'جواز، فیس او انجنیري', system: true, activePhase: 1 },
  { code: '5980', type: 'expense', normalBalance: 'debit', parentCode: '5000', nameEn: 'Project & Site Overhead', nameFa: 'مصارف اداری و محافظت ساحه', namePs: 'د ساحې اداري لګښتونه', system: true, activePhase: 1 },
  { code: '5990', type: 'expense', normalBalance: 'debit', parentCode: '5000', nameEn: 'Other Project Expenses', nameFa: 'سایر مصارف پروژه', namePs: 'د پروژې نور لګښتونه', system: true, activePhase: 1,
    description: 'Default/fallback bucket for unmapped or custom expense categories.' },

  { code: '5310', parentCode: '5300', type: 'expense', normalBalance: 'debit', nameEn: 'Cement & mortar', nameFa: 'سمنت و مساله', namePs: 'سمنټ او مساله', system: true, activePhase: 1 },
  { code: '5410', parentCode: '5400', type: 'expense', normalBalance: 'debit', nameEn: 'Sand', nameFa: 'ریگ', namePs: 'شګه', system: true, activePhase: 1 },
  { code: '5420', parentCode: '5400', type: 'expense', normalBalance: 'debit', nameEn: 'Gravel & aggregates', nameFa: 'جغل و سنگ‌ریزه', namePs: 'جغل او د ډبرو مواد', system: true, activePhase: 1 },
  { code: '5510', parentCode: '5500', type: 'expense', normalBalance: 'debit', nameEn: 'Bricks', nameFa: 'خشت', namePs: 'خښتې', system: true, activePhase: 1 },
  { code: '5520', parentCode: '5500', type: 'expense', normalBalance: 'debit', nameEn: 'Concrete blocks', nameFa: 'بلاک کانکریتی', namePs: 'کانکریټي بلاکونه', system: true, activePhase: 1 },
  { code: '5530', parentCode: '5500', type: 'expense', normalBalance: 'debit', nameEn: 'Masonry mortar', nameFa: 'مسالهٔ خشت‌کاری', namePs: 'د خښتو مساله', system: true, activePhase: 1 },
  { code: '5911', parentCode: '5910', type: 'expense', normalBalance: 'debit', nameEn: 'Timber & plywood', nameFa: 'چوب و تختهٔ قالب', namePs: 'لرګي او د قالب تختې', system: true, activePhase: 1 },
  { code: '5912', parentCode: '5910', type: 'expense', normalBalance: 'debit', nameEn: 'Formwork fittings & nails', nameFa: 'وسایل قالب و میخ', namePs: 'د قالب وسایل او مېخونه', system: true, activePhase: 1 },
  { code: '5921', parentCode: '5920', type: 'expense', normalBalance: 'debit', nameEn: 'Electrical cables & conduit', nameFa: 'کیبل برق و پایپ برق', namePs: 'برېښنايي کیبل او پایپ', system: true, activePhase: 1 },
  { code: '5922', parentCode: '5920', type: 'expense', normalBalance: 'debit', nameEn: 'Switches, sockets & fixtures', nameFa: 'سویچ، ساکت و چراغ', namePs: 'سویچ، ساکټ او څراغ', system: true, activePhase: 1 },
  { code: '5923', parentCode: '5920', type: 'expense', normalBalance: 'debit', nameEn: 'Distribution boards & protection', nameFa: 'تابلو برق و وسایل محافظتی', namePs: 'برېښنايي تابلو او ساتندوی وسایل', system: true, activePhase: 1 },
  { code: '5931', parentCode: '5930', type: 'expense', normalBalance: 'debit', nameEn: 'Water pipes & fittings', nameFa: 'نل آب و اتصالات', namePs: 'د اوبو نلونه او نښلوونکي', system: true, activePhase: 1 },
  { code: '5932', parentCode: '5930', type: 'expense', normalBalance: 'debit', nameEn: 'Drainage & sewer pipes', nameFa: 'نل فاضلاب و زهکشی', namePs: 'د فاضلاب او اوبو ایستلو نلونه', system: true, activePhase: 1 },
  { code: '5933', parentCode: '5930', type: 'expense', normalBalance: 'debit', nameEn: 'Sanitary fittings', nameFa: 'وسایل تشناب', namePs: 'د تشناب وسایل', system: true, activePhase: 1 },
  { code: '5941', parentCode: '5940', type: 'expense', normalBalance: 'debit', nameEn: 'Doors & frames', nameFa: 'دروازه و چوکاټ', namePs: 'دروازې او چوکاټونه', system: true, activePhase: 1 },
  { code: '5942', parentCode: '5940', type: 'expense', normalBalance: 'debit', nameEn: 'Windows, glass & aluminum', nameFa: 'کلکین، شیشه و المونیم', namePs: 'کړکۍ، ښیښه او المونیم', system: true, activePhase: 1 },
  { code: '5943', parentCode: '5940', type: 'expense', normalBalance: 'debit', nameEn: 'Locks & door hardware', nameFa: 'قفل و وسایل دروازه', namePs: 'قلفونه او د دروازو وسایل', system: true, activePhase: 1 },
  { code: '5951', parentCode: '5950', type: 'expense', normalBalance: 'debit', nameEn: 'Tiles & ceramics', nameFa: 'کاشی و سرامیک', namePs: 'کاشي او سرامیک', system: true, activePhase: 1 },
  { code: '5952', parentCode: '5950', type: 'expense', normalBalance: 'debit', nameEn: 'Natural stone & marble', nameFa: 'سنگ طبیعی و مرمر', namePs: 'طبیعي ډبرې او مرمر', system: true, activePhase: 1 },
  { code: '5953', parentCode: '5950', type: 'expense', normalBalance: 'debit', nameEn: 'Plaster & gypsum', nameFa: 'گچ و پلستر', namePs: 'ګچ او پلستر', system: true, activePhase: 1 },
  { code: '5954', parentCode: '5950', type: 'expense', normalBalance: 'debit', nameEn: 'Paint & coatings', nameFa: 'رنگ و پوشش', namePs: 'رنګ او پوښښ', system: true, activePhase: 1 },
  { code: '5955', parentCode: '5950', type: 'expense', normalBalance: 'debit', nameEn: 'Primer & putty', nameFa: 'پرایمر و بتونه', namePs: 'پرایمر او بتونه', system: true, activePhase: 1 },
  { code: '5956', parentCode: '5950', type: 'expense', normalBalance: 'debit', nameEn: 'Thinner, solvents & paint tools', nameFa: 'تینر، حلال و وسایل رنگمالی', namePs: 'تینر، محلولونه او د رنګمالۍ وسایل', system: true, activePhase: 1 },
  { code: '5957', parentCode: '5950', type: 'expense', normalBalance: 'debit', nameEn: 'Decoration & ceiling materials', nameFa: 'مواد دیکور و سقف کاذب', namePs: 'د سینګار او کاذب چت مواد', system: true, activePhase: 1 },
  { code: '5958', parentCode: '5950', type: 'expense', normalBalance: 'debit', nameEn: 'Flooring & finishing accessories', nameFa: 'فرش و وسایل تکمیلی', namePs: 'فرش او تکمیلي وسایل', system: true, activePhase: 1 },
  { code: '5959', parentCode: '5950', type: 'expense', normalBalance: 'debit', nameEn: 'Waterproofing & insulation', nameFa: 'عایق رطوبت و حرارت', namePs: 'د رطوبت او تودوخې عایق', system: true, activePhase: 1 },
  { code: '5961', parentCode: '5960', type: 'expense', normalBalance: 'debit', nameEn: 'Ventilation & air conditioning', nameFa: 'تهویه و ایرکندیشن', namePs: 'هوا بدلول او ایرکنډیشن', system: true, activePhase: 1 },
  { code: '5962', parentCode: '5960', type: 'expense', normalBalance: 'debit', nameEn: 'Lift installation materials', nameFa: 'مواد نصب لیفت', namePs: 'د لیفټ د نصب مواد', system: true, activePhase: 1 },
  { code: '5963', parentCode: '5960', type: 'expense', normalBalance: 'debit', nameEn: 'Facade & external cladding', nameFa: 'نما و پوشش بیرونی', namePs: 'مخ او بهرنی پوښښ', system: true, activePhase: 1 },
  { code: '5964', parentCode: '5960', type: 'expense', normalBalance: 'debit', nameEn: 'Landscaping & external works', nameFa: 'محوطه‌سازی و کارهای بیرونی', namePs: 'د انګړ جوړول او بهرني کارونه', system: true, activePhase: 1 },

  /* ================= 6000 OPERATING & ADMIN (COMPANY) ================= */
  { code: '6000', type: 'expense', normalBalance: 'debit', nameEn: 'Operating & Admin Expenses', nameFa: 'مصارف اداری عمومی (غیر از پروژه)', namePs: 'عمومي اداري لګښتونه', isHeader: true, system: true,
    description: 'Company-level overheads — NOT project costs, kept out of project P&L.' },
  { code: '6100', type: 'expense', normalBalance: 'debit', parentCode: '6000', nameEn: 'Office Rent & Admin', nameFa: 'کرایه و مصارف دفتر مرکزی', namePs: 'د مرکزي دفتر کرایه او لګښتونه', system: true, activePhase: 2 },
  { code: '6200', type: 'expense', normalBalance: 'debit', parentCode: '6000', nameEn: 'Admin Salaries', nameFa: 'معاشات کارمندان اداری', namePs: 'د اداري کارکوونکو معاشونه', system: true, activePhase: 2 },
  { code: '6300', type: 'expense', normalBalance: 'debit', parentCode: '6000', nameEn: 'Marketing & Advertising', nameFa: 'بازاریابی و تبلیغات', namePs: 'بازار موندنه او اعلانونه', system: true, activePhase: 2 },
  { code: '6900', type: 'expense', normalBalance: 'debit', parentCode: '6000', nameEn: 'Other Operating Expenses', nameFa: 'سایر مصارف عملیاتی', namePs: 'نور عملیاتي لګښتونه', system: true, activePhase: 2 },

  /* ========================= 7000 FINANCE COSTS ======================= */
  { code: '7000', type: 'expense', normalBalance: 'debit', nameEn: 'Finance Costs', nameFa: 'مصارف مالی', namePs: 'مالي لګښتونه', isHeader: true, system: true },
  { code: '7100', type: 'expense', normalBalance: 'debit', parentCode: '7000', nameEn: 'Bank Charges', nameFa: 'فیس بانک', namePs: 'د بانک فیس', system: true, activePhase: 2 },
  { code: '7200', type: 'expense', normalBalance: 'debit', parentCode: '7000', nameEn: 'Loan & Finance Cost', nameFa: 'مصارف قرض و تمویل', namePs: 'د پور لګښت او ګټه', system: true, activePhase: 2 },

  /* ============================ 8000 OTHER ============================ */
  { code: '8000', type: 'expense', normalBalance: 'debit', nameEn: 'Other Accounts', nameFa: 'سایر حساب‌ها', namePs: 'نور حسابونه', isHeader: true, system: true },
  { code: '8100', type: 'expense', normalBalance: 'debit', parentCode: '8000', nameEn: 'Accounting Adjustments', nameFa: 'تعدیلات حسابداری', namePs: 'د محاسبې سمونونه', system: true, activePhase: 2 },
  {code:'8210',parentCode:'8000',type:'expense',normalBalance:'debit',nameEn:'Currency Rounding',nameFa:'تفاوت گردکردن ارز',namePs:'د اسعارو د ګردولو توپیر',system:true,activePhase:1},
  { code: '8200', type: 'expense', normalBalance: 'debit', parentCode: '8000', nameEn: 'Suspense Account', nameFa: 'حساب موقت (در انتظار طبقه‌بندی)', namePs: 'لنډمهاله حساب', system: true, activePhase: 2,
    description: 'Temporary parking for unclassifiable items until corrected.' },
];

/* ------------------------------------------------------------------ */
/* Lookup helpers                                                      */
/* ------------------------------------------------------------------ */

const ACCOUNT_INDEX: Map<string, Account> = new Map(CHART_OF_ACCOUNTS.map(a => [a.code, a]));

export function getAccount(code: string): Account | undefined {
  return ACCOUNT_INDEX.get(code);
}

export function getAccountName(code: string, lang: 'en' | 'fa' | 'ps' = 'en'): string {
  const acc = ACCOUNT_INDEX.get(code);
  if (!acc) return code;
  return lang === 'ps' ? acc.namePs : lang === 'en' ? acc.nameEn : acc.nameFa;
}

export function isPostingAccount(code: string): boolean {
  const acc = ACCOUNT_INDEX.get(code);
  return !!acc && !acc.isHeader;
}

/** All postable (non-header) accounts */
export function getPostingAccounts(): Account[] {
  return CHART_OF_ACCOUNTS.filter(a => !a.isHeader);
}

/** Accounts belonging to a top-level group, e.g. groupCode '5000' */
export function getAccountsInGroup(groupCode: string): Account[] {
  return CHART_OF_ACCOUNTS.filter(a => a.code.startsWith(groupCode[0]));
}

/**
 * Walk `parentCode` chain upward and return ancestor codes (closest first).
 */
export function getAncestorCodes(code: string): string[] {
  const chain: string[] = [];
  let current = ACCOUNT_INDEX.get(code);
  let guard = 0;
  while (current?.parentCode && guard++ < 10) {
    chain.push(current.parentCode);
    current = ACCOUNT_INDEX.get(current.parentCode);
  }
  return chain;
}

/* ------------------------------------------------------------------ */
/* Expense category -> account mapping                                 */
/* ------------------------------------------------------------------ */

/**
 * The app never stores account codes — users pick business categories
 * (e.g. 'Cement', 'Formwork', 'Daily Labour Wages', custom categories...).
 * This map routes every known category (English ids, English labels, Dari
 * labels from the modals, and legacy seed values) to its project-cost
 * account. Lookup is normalized (lowercase/trim) and falls back to 5990.
 */
export const EXPENSE_CATEGORY_ACCOUNT_MAP: Record<string, string> = {
  // Older imported labels keep their original accounting classification.
  'سیمنت': '5300',
  'آجر': '5500',

  // ---- 5100 Steel ----
  'steel': '5100', 'steel / rebar': '5100', 'steel/rebar': '5100', 'rebar': '5100',
  'سیخ': '5100', 'سیخ‌گول': '5100', 'سیخ‌گول (فولاد)': '5100',

  // ---- 5200 Concrete ----
  'concrete': '5200', 'concrete & pump': '5200', 'concrete & pumping': '5200',
  'کانکریت': '5200', 'کانکریت و پمپ': '5200',

  // ---- 5300 Cement ----
  'cement': '5300', 'سمنت': '5300',
  'materials': '5300', 'building materials': '5300',

  // ---- 5400 Sand & Gravel ----
  'sand': '5400', 'gravel': '5400', 'sand & gravel': '5400', 'ریگ': '5400',

  // ---- 5500 Masonry ----
  'masonry': '5500', 'brick / block masonry': '5500', 'blocks': '5500',
  'bricks': '5500', 'بلاک': '5500', 'خشت': '5500',

  // ---- 5600 Labour ----
  'labour': '5600', 'labor': '5600', 'direct labour': '5600', 'wages': '5600',
  'daily_labour': '5600', 'daily labour wages': '5600',
  'worker_food': '5600', 'worker meals & food': '5600',
  'مزد': '5600', 'مزد کارگران روزمزد': '5600', 'نان و غذای کارگران': '5600',

  // ---- 5700 Machinery ----
  'machinery': '5700', 'machinery & equipment rental': '5700', 'equipment': '5700',

  // ---- 5800 Transportation ----
  'transportation': '5800', 'transportation & logistics': '5800',
  'transportation & fares': '5800', 'transport': '5800',
  'کرایه موتر و ترانسپورت': '5800',

  // ---- 5900 Utilities & Fuel ----
  'utilities': '5900', 'electricity': '5900', 'water': '5900',
  'fuel': '5900', 'fuel & generator': '5900',

  // ---- 5910 Formwork & Scaffolding ----
  'formwork': '5910', 'formwork & carpentry': '5910', 'formwork & shuttering': '5910',
  'formworkcontract': '5910',
  'scaffolding': '5910', 'scaffolding (rental & fix)': '5910',
  'wood_supplier': '5910', 'plywood & wood supplier': '5910',
  'tie_wire': '5910', 'tie wire & steel fixer': '5910',
  'قالب‌بندی و نجاری (تخته و چوب)': '5910', 'اسکافولد و داربست (کرایه و بستن)': '5910',

  // ---- 5920 Electrical ----
  'electrical': '5920', 'electrical works': '5920', 'برق‌کاری و تأسیسات برقی': '5920',

  // ---- 5930 Plumbing ----
  'plumbing': '5930', 'plumbing & sanitary': '5930',

  // ---- 5940 Carpentry, Doors & Windows ----
  'carpentry': '5940', 'doors': '5940', 'windows': '5940', 'windows & glazing': '5940',
  'doors & windows': '5940', 'doorswindows': '5940',

  // ---- 5950 Tiles, Paint & Finishing ----
  'paint': '5950', 'paint & finishes': '5950', 'painting': '5950', 'painting & finishes': '5950',
  'finishing': '5950', 'finishing materials': '5950',
  'tiles': '5950', 'tiles & flooring': '5950',
  'plaster': '5950', 'plaster & finishing': '5950',

  // ---- 5960 Elevator & Excavation ----
  'elevator': '5960', 'elevator & lifts': '5960',
  'excavation': '5960', 'excavation & shoring': '5960',

  // ---- 5970 Permits, Fees & Engineering ----
  'taxes': '5970', 'taxes & municipal fees': '5970',
  'landpermits': '5970', 'land / municipality / permits': '5970',
  'municipality': '5970', 'district / municipality fee': '5970',
  'تعرفه ناحیه و شاروالی': '5970',
  'engineering': '5970', 'engineering & design': '5970', 'permits': '5970',

  // ---- 5980 Site Overhead ----
  'security': '5980', 'site security guard': '5980',

  // ---- 5990 Other ----
  'other': '5990', 'other construction expenses': '5990', 'other construction costs': '5990',
  'miscellaneous': '5990', 'miscellaneous expenses': '5990',
  'مصارف متفرقه و پیش‌بینی‌نشده': '5990',
};

/** Translate known legacy system categories while retaining custom user-written names. */
export function expenseDisplayCode(category:string):string {
 const key=category.trim().toLowerCase();return Object.hasOwn(EXPENSE_CATEGORY_ACCOUNT_MAP,key)?EXPENSE_CATEGORY_ACCOUNT_MAP[key]:category;
}

export const DEFAULT_EXPENSE_ACCOUNT = '5990';

/** Substrings checked (lower-cased) when an exact map entry is missing */
const FUZZY_CATEGORY_HINTS: Array<[string, string]> = [
  ['steel', '5100'], ['rebar', '5100'], ['سیخ', '5100'], ['آهن', '5100'],
  ['concrete', '5200'], ['کانکریت', '5200'], ['pump', '5200'],
  ['cement', '5300'], ['سمنت', '5300'],
  ['sand', '5400'], ['gravel', '5400'], ['ریگ', '5400'], ['شن', '5400'],
  ['masonry', '5500'], ['block', '5500'], ['brick', '5500'], ['بلاک', '5500'], ['خشت', '5500'],
  ['labour', '5600'], ['labor', '5600'], ['wage', '5600'], ['worker', '5600'], ['کارگر', '5600'], ['مزد', '5600'],
  ['machin', '5700'], ['equipment', '5700'], ['mixer', '5700'], ['crane', '5700'], ['حفار', '5700'],
  ['transport', '5800'], ['فلیت', '5800'], ['کرایه موتر', '5800'],
  ['fuel', '5900'], ['diesel', '5900'], ['generator', '5900'], ['تیل', '5900'], ['برق', '5920'],
  ['formwork', '5910'], ['scaffold', '5910'], ['shutter', '5910'], ['قالب', '5910'], ['داربست', '5910'], ['tie wire', '5910'],
  ['electric', '5920'], ['wire', '5920'],
  ['plumb', '5930'], ['pipe', '5930'], ['sanitary', '5930'], ['نل', '5930'],
  ['door', '5940'], ['window', '5940'], ['carpent', '5940'], ['نجار', '5940'], ['ورک', '5940'],
  ['paint', '5950'], ['tile', '5950'], ['plaster', '5950'], ['finish', '5950'], ['نقاش', '5950'], ['کاشي', '5950'], ['کاشی', '5950'],
  ['elevator', '5960'], ['lift', '5960'], ['excavat', '5960'], ['لیفتر', '5960'], ['حفار', '5960'],
  ['permit', '5970'], ['municipal', '5970'], ['tax', '5970'], ['engineering', '5970'], ['جواز', '5970'], ['شاروال', '5970'], ['ناحیه', '5970'],
  ['security', '5980'], ['guard', '5980'], ['نگهبان', '5980'],
  ['food', '5600'], ['نان', '5600'],
];

/**
 * Resolve any user-facing expense category (stored on `Expense.category`)
 * to its project-cost account code. Never fails — falls back to 5990.
 */
export function resolveExpenseAccount(category?: string): string {
  if (typeof category!=='string'||!category) return DEFAULT_EXPENSE_ACCOUNT;
  const key = category.trim().toLowerCase();
  if (/^\d{4}$/.test(key) && CHART_OF_ACCOUNTS.some(a=>a.code===key && a.type==='expense' && !a.isHeader)) return key;
  if (Object.hasOwn(EXPENSE_CATEGORY_ACCOUNT_MAP,key)) return EXPENSE_CATEGORY_ACCOUNT_MAP[key];
  for (const [hint, code] of FUZZY_CATEGORY_HINTS) {
    if (key.includes(hint)) return code;
  }
  return DEFAULT_EXPENSE_ACCOUNT;
}

/* ------------------------------------------------------------------ */
/* Well-known account codes (single source of truth for the engine)    */
/* ------------------------------------------------------------------ */

export const ACC = {
  CASH_AFN: '1110',
  CASH_USD: '1120',
  BANK: '1130',
  HAWALA: '1140',
  APARTMENT_RECEIVABLES: '1200',
  ADVANCES_TO_SUPPLIERS: '1300',
  MATERIAL_INVENTORY: '1400',
  WIP: '1500',
  SUPPLIER_PAYABLES: '2200',
  CONTRACTOR_PAYABLES: '2250',
  CUSTOMER_ADVANCES: '2300',
  RETENTION_PAYABLE: '2400',
  TAXES_PAYABLE: '2600',
  OTHER_LIABILITIES: '2900',
  PARTNERS_CAPITAL: '3100',
  ADDITIONAL_INVESTMENTS: '3200',
  PARTNER_DRAWINGS: '3300',
  UNIT_SALES: '4100',
  OTHER_REVENUE: '4300',
  COGS: '5050',
  STEEL: '5100',
  CONCRETE: '5200',
  OTHER_PROJECT_EXPENSES: '5990',
  SUSPENSE: '8200',
  CURRENCY_ROUNDING: '8210',
} as const;
