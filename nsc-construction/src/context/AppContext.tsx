import {exportLocalFiles,restoreLocalFiles} from '../services/localFiles';
import {roundMoney} from '../utils/money';
import {usePersonalAppearance} from '../modern/themes';
import {computeSellableAreaM2} from '../utils/wipAllocation';
import {inventoryUnits} from '../utils/apartmentFinance';
import type {CreditNote} from '../types';
import {clientSourceCollection} from '../utils/credits';
import {canAccess as transferAccess} from '../modern/model';
import type {ProjectLedgerTransfer} from '../types';
import {payrollAmounts} from '../utils/payroll';
import {installmentBalances,saleDate} from '../utils/apartmentFinance';
import {canAccess} from '../modern/model';
import {resolvePreferredLanguage,todayISO} from '../modern/presentation';
import React, { createContext, useContext, useState, useEffect, useMemo } from 'react';
import { 
  Language, 
  User, 
  Project, 
  Expense, 
  SteelRecord, 
  ConcreteRecord, 
  Contractor, 
  Supplier, 
  ApartmentUnit, 
  Payment, 
  PaymentStatus,
  DocumentRecord, 
  AuditLog, 
  ProjectFinancialSummary,
  AppSettings,
  AppTheme,
  ProjectPartner,
  ProjectInvestment,
  ProjectTransferEvent,
  GoogleDriveConfig,
  InstallmentRecord,
  InstallmentPaymentRecord,
  SystemReminderNotification,
  JournalEntry,
  AccountBalance,
  TrialBalanceRow,
  ProjectBudget,
  BudgetLineReport,
  UserPermissions,
  UserActivityEvent,
  CustomBillDesign,
  CustomActionButton,
  LaborRecord,
  FixedAsset,
  PartnerEquityTransfer
} from '../types';
import { 
  initialUsers, 
  initialProjects, 
  initialContractors, 
  initialSuppliers, 
  initialSteelRecords, 
  initialConcreteRecords, 
  initialExpenses, 
  initialApartments, 
  initialPayments, 
  initialDocuments, 
  initialAuditLogs,
  initialProjectPartners,
  initialProjectInvestments,
  initialProjectBudgets
} from '../data/seedData';
import { clearDriveToken, initAuth, googleSignIn, googleSignOut, getAccessToken } from '../services/googleDriveAuth';
import { getOrCreateFolder, uploadFileToGoogleDrive } from '../services/googleDriveService';
import { calculateInstallmentStatus, normalizeApartmentInstallments, generateSmartReminders } from '../utils/installmentUtils';
import { buildJournal, computeAccountBalances, buildTrialBalance } from '../utils/journalEngine';
import { CHART_OF_ACCOUNTS, getAccountName } from '../data/chartOfAccounts';
import type { Account } from '../data/chartOfAccounts';
import { computeBudgetReport } from '../utils/budgetUtils';
import { backendApi } from '../services/backendApi';
import {previewLicenseUsers} from '../modern/licenseModel';
import {designText} from '../modern/designModel';
import {translate as uiText,locales as uiLocales,numberingSystems} from '../modern/i18n';
import { useWorkspaceSync, WorkspaceStatus } from '../services/workspaceSync';
import {blankTestMode,blankTestUser,blankTestStoragePrefix} from '../config/testMode';

interface AppContextType {
  language: Language;
  setLanguage: (lang: Language) => void;
  currentUser: User | null;
  workspaceStatus: WorkspaceStatus;
  workspaceCompany: string | null;
  switchCompanyWorkspace: (id:string|null) => void;
  workspaceError: string;
  retryWorkspaceSave: () => void;
  transitionFinancial: (collection:string,id:string,status:string,details?:{date:string;reason:string;amount?:number}) => Promise<void>;
  login: (usernameOrEmail: string, password?: string, loginType?: 'admin' | 'employee') => Promise<{ success: boolean; error?: string;code?:string }>;
  logout: () => void;

  users: User[];
  addUser: (user: Omit<User, 'id' | 'createdAt'>) => void;
  /** دفتر هفت: per-user usage telemetry (features visited, login/logout moments) */


  updateUser: (id: string, updates: Partial<User>) => void;

  toggleUserActive: (id: string) => void;


  requestPasswordReset: (email: string) => Promise<{ success: boolean; otpCode?: string; error?: string }>;
  completePasswordReset: (email: string, otpCode: string, newPassword: string) => Promise<{ success: boolean; error?: string;code?:string }>;

  toggleDarkMode: () => void;
  activeTheme: AppTheme;
  setTheme: (theme: AppTheme) => void;
  themeSchedule: 'auto' | 'light' | 'dark';
  setThemeSchedule: (mode: 'auto' | 'light' | 'dark') => void;

  projects: Project[];
  currentProject: Project | null;
  setCurrentProjectId: (id: string) => void;
  addProject: (project: Omit<Project, 'id' | 'createdAt'>) => Project;
  updateProject: (id: string, updates: Partial<Project>) => void;
  deleteProject: (id: string) => void;

  projectPartners: ProjectPartner[];
  projectInvestments: ProjectInvestment[];
  creditNotes:CreditNote[];addCreditNote:(data:any)=>void;updateCreditNote:(id:string,data:any)=>void;deleteCreditNote:(id:string)=>void;
  projectTransfers: ProjectLedgerTransfer[];
  addProjectTransfer:(data:any)=>void;updateProjectTransfer:(id:string,data:any)=>void;deleteProjectTransfer:(id:string)=>void;
  addProjectPartner: (partner: Omit<ProjectPartner, 'id' | 'createdAt'>) => ProjectPartner;
  updateProjectPartner: (id: string, updates: Partial<ProjectPartner>) => void;
  deleteProjectPartner: (id: string) => void;
  addProjectInvestment: (investment: Omit<ProjectInvestment, 'id' | 'createdAt'>) => ProjectInvestment;
  updateProjectInvestment: (id: string, updates: Partial<ProjectInvestment>) => void;
  deleteProjectInvestment: (id: string) => void;
  partnerEquityTransfers: PartnerEquityTransfer[];
  transferPartnerEquity: (transfer: Omit<PartnerEquityTransfer, 'id' | 'createdAt'>, newBuyer?: {name:string;phone?:string}) => PartnerEquityTransfer;


  expenses: Expense[];
  addExpense: (expense: Omit<Expense, 'id' | 'createdAt' | 'createdBy'>) => void;
  updateExpense: (id: string, updates: Partial<Expense>) => void;
  deleteExpense: (id: string) => void;

  steelRecords: SteelRecord[];
  addSteelRecord: (record: Omit<SteelRecord, 'id' | 'tons' | 'totalAmount' | 'remainingBalance' | 'createdAt'>) => void;
  updateSteelRecord: (id: string, updates: Partial<SteelRecord>) => void;
  deleteSteelRecord: (id: string) => void;
  concreteRecords: ConcreteRecord[];
  addConcreteRecord: (record: Omit<ConcreteRecord, 'id' | 'totalAmount' | 'remainingBalance' | 'createdAt'>) => void;
  updateConcreteRecord: (id: string, updates: Partial<ConcreteRecord>) => void;
  deleteConcreteRecord: (id: string) => void;
  contractors: Contractor[];
  addContractor: (contractor: Omit<Contractor, 'id' | 'createdAt'>) => void;
  updateContractor: (id: string, updates: Partial<Contractor>) => void;
  deleteContractor: (id: string) => void;
  suppliers: Supplier[];
  addSupplier: (supplier: Omit<Supplier, 'id' | 'createdAt'>) => void;
  updateSupplier: (id: string, updates: Partial<Supplier>) => void;
  deleteSupplier: (id: string) => void;
  laborRecords: LaborRecord[];
  addLaborRecord: (record: Omit<LaborRecord, 'id' | 'createdAt'>) => void;
  updateLaborRecord: (id:string,updates:Partial<LaborRecord>)=>void;
  deleteLaborRecord: (id: string) => void;
  assets: FixedAsset[];
  addAsset: (asset: Omit<FixedAsset, 'id' | 'createdAt'>) => void;
  updateAsset: (id: string, updates: Partial<FixedAsset>) => void;
  deleteAsset: (id: string) => void;
  apartments: ApartmentUnit[];
  addApartment: (unit: Omit<ApartmentUnit, 'id' | 'salePrice' | 'remainingBalance' | 'createdAt'> & { salePrice?: number }) => void;
  updateApartment: (id: string, updates: Partial<ApartmentUnit>) => void;
  deleteApartment: (id: string) => void;


  addInstallmentToApartment: (apartmentId: string, installmentData: Omit<InstallmentRecord, 'id' | 'remainingAmount' | 'status' | 'paymentHistory'>) => void;

  // Notifications & Reminders
  notifications: SystemReminderNotification[];

  payments: Payment[];
  addPayment: (payment: Omit<Payment, 'id' | 'createdAt' | 'createdBy'>) => void;
  updatePayment: (id: string, updates: Partial<Payment>) => void;
  deletePayment: (id: string) => void;
  appSettings: AppSettings;
  updateAppSettings: (updates: Partial<AppSettings>) => void;
  publishWebsiteStudio: (payload:any)=>Promise<any>;

  documents: DocumentRecord[];
  addDocument: (doc: Omit<DocumentRecord, 'id' | 'createdAt'>) => void;
  updateDocument: (id:string,updates:Partial<DocumentRecord>) => void;
  deleteDocument: (id: string) => void;
  auditLogs: AuditLog[];

  /**
   * Accounting layer (Chart of Accounts). The double-entry journal is a
   * pure projection derived from the business records — it is rebuilt
   * automatically whenever expenses/steel/concrete/payments/apartments/
   * investments change, so it can never drift out of sync with the UI data.
   */
  chartOfAccounts: Account[];
  getAccountDisplayName: (code: string, lang?: Language) => string;

  getProjectJournal: (projectId: string) => JournalEntry[];
  /** Project Budget: one budget line per (project, COA account) */
  projectBudgets: ProjectBudget[];

  getBudgetReport: (projectId: string) => BudgetLineReport[];
  addProjectBudget: (budget: Omit<ProjectBudget, 'id' | 'createdAt' | 'createdBy'>) => ProjectBudget;
  updateProjectBudget: (id: string, updates: Partial<ProjectBudget>) => void;
  deleteProjectBudget: (id: string) => void;
  searchQuery: string;
  setSearchQuery: (q: string) => void;

  exportJsonBackup: () => Promise<void>;
  restoreJsonBackup: (jsonContent: string) => Promise<{ success: boolean; error?: string }>;

  formatCurrency: (amount: number | undefined, currency?: string) => string;
  formatNumber: (value: number | undefined, decimals?: number) => string;
  isDarkMode: boolean;
  // Google Drive integration
  isGoogleDriveConnected: boolean;


  connectGoogleDrive: () => Promise<any>;
  disconnectGoogleDrive: () => Promise<void>;
  backupToGoogleDrive: () => Promise<any>;

}

const AppContext = createContext<AppContextType | undefined>(undefined);
const IS_PREVIEW = import.meta.env.VITE_PREVIEW === 'true';
const STORAGE_PREFIX = blankTestMode?blankTestStoragePrefix:IS_PREVIEW ? 'nsc_preview_' : 'nik_smart_count_';

function loadFromStorage<T>(key: string, fallback: T): T {
  if(blankTestMode){
    if(key==='currentUser')return {...blankTestUser} as T;
    if(Array.isArray(fallback))fallback=(key==='users'?[{...blankTestUser}]:[]) as T;
    if(key==='currentProjId')fallback='' as T;
  }
  if (!IS_PREVIEW && key === 'lang') { try { const preferred=localStorage.getItem('nsc_ui_language');if(['en','fa','ps'].includes(preferred||''))return preferred as T; } catch {} }
  if (!IS_PREVIEW) return (Array.isArray(fallback) ? [] : key === 'currentProjId' ? '' : fallback) as T;
  try {
    const saved = localStorage.getItem(STORAGE_PREFIX + key);
    if (saved) return JSON.parse(saved);
  } catch (e) {
    console.error(`Failed to load ${key} from storage`, e);
  }
  return fallback;
}

function saveToStorage<T>(key: string, value: T): void {
  if (!IS_PREVIEW) {if(key==='lang'){try{localStorage.setItem('nsc_ui_language',String(value));}catch{}}return;}
  try {
    localStorage.setItem(STORAGE_PREFIX + key, JSON.stringify(value));
  } catch (e) {
    console.error(`Failed to save ${key} to storage`, e);
  }
}

export const AppProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [workspaceCompany,setWorkspaceCompany] = useState<string|null>(null);
  const switchCompanyWorkspace=(id:string|null)=>{backendApi.setWorkspaceCompany(id);setWorkspaceCompany(id);};
  const [language, setLanguageState] = useState<Language>(() => loadFromStorage<Language>('lang', 'en'));
  const [users, setUsers] = useState<User[]>(() => loadFromStorage('users', initialUsers));
  const [currentUser, setCurrentUser] = useState<User | null>(() => {
    return loadFromStorage<User | null>('currentUser', IS_PREVIEW ? initialUsers[1] : null); // Demo account is restricted to preview builds
  });
  const [projects, setProjects] = useState<Project[]>(() => loadFromStorage('projects', initialProjects));
  const [currentProjectId, setCurrentProjectIdState] = useState<string>(() => loadFromStorage('currentProjId', initialProjects[0]?.id || ''));
  const [expenses, setExpenses] = useState<Expense[]>(() => loadFromStorage('expenses', initialExpenses));
  const [steelRecords, setSteelRecords] = useState<SteelRecord[]>(() => loadFromStorage('steel', initialSteelRecords));
  const [concreteRecords, setConcreteRecords] = useState<ConcreteRecord[]>(() => loadFromStorage('concrete', initialConcreteRecords));
  const [contractors, setContractors] = useState<Contractor[]>(() => loadFromStorage('contractors', initialContractors));
  const [suppliers, setSuppliers] = useState<Supplier[]>(() => loadFromStorage('suppliers', initialSuppliers));
  const [apartments, setApartments] = useState<ApartmentUnit[]>(() => loadFromStorage('apartments', initialApartments));
  const initialLaborRecords: LaborRecord[] = [
    {
      id: 'lab-1',
      projectId: 'p1',
      workerName: 'استاد رحیم گل‌کار',
      role: 'گل‌کار و سنگ‌کار نما',
      phone: '0789123456',
      workPeriod: 'ماه جاری',
      daysWorked: 26,
      dailyRate: 1200,
      grossWage: 31200,
      advanceDeduction: 5000,
      netPayable: 26200,
      paymentStatus: 'paid',
      paidDate: '2026-09-30',
      approvedBy: 'انجنیر ناظر پروژه',
      notes: 'تکمیل سنگ‌کاری دیوارهای لابی',
      createdAt: new Date().toISOString()
    },
    {
      id: 'lab-2',
      projectId: 'p1',
      workerName: 'شیرخان آرماتوربند',
      role: 'سرکارگر بافت سیخ گول',
      phone: '0772345678',
      workPeriod: 'ماه جاری',
      daysWorked: 24,
      dailyRate: 1100,
      grossWage: 26400,
      advanceDeduction: 4000,
      netPayable: 22400,
      paymentStatus: 'paid',
      paidDate: '2026-09-30',
      approvedBy: 'انجنیر ناظر پروژه',
      notes: 'سیخ‌بندی سقف طبقه ۳',
      createdAt: new Date().toISOString()
    },
    {
      id: 'lab-3',
      projectId: 'p1',
      workerName: 'غلام‌حیدر کارگر ساده',
      role: 'تخلیه مصالح و بتن‌ریزی',
      phone: '0798765432',
      workPeriod: 'ماه جاری',
      daysWorked: 28,
      dailyRate: 600,
      grossWage: 16800,
      advanceDeduction: 3000,
      netPayable: 13800,
      paymentStatus: 'pending',
      approvedBy: 'سرپرست کارگاه',
      notes: 'کمک در تخلیه سمنت و پاک‌کاری',
      createdAt: new Date().toISOString()
    }
  ];

  const initialAssets: FixedAsset[] = [
    {
      id: 'ast-1',
      assetTag: 'AST-101',
      name: 'تاور کرین ۶۰ متر لیبهر (Liebherr)',
      category: 'machinery',
      assignedProjectId: 'p1',
      purchaseDate: '2024-03-15',
      purchaseCost: 85000,
      salvageValue: 15000,
      usefulLifeYears: 8,
      monthlyDepreciation: 729,
      accumulatedDepreciation: 21870,
      currentBookValue: 63130,
      runningHours: 1420,
      hourlyOperatingRate: 25,
      serialNumber: 'LH-60EC-9921',
      assignedPerson: 'استاد صمد جرثقیل‌ران',
      status: 'active',
      notes: 'سرویس دوره‌ای سیم بکسل و هیدرولیک انجام شد',
      createdAt: new Date().toISOString()
    },
    {
      id: 'ast-2',
      assetTag: 'AST-102',
      name: 'پمپ کانکریت هوایی ۳۶ متری شوئینگ',
      category: 'machinery',
      assignedProjectId: 'p1',
      purchaseDate: '2024-06-10',
      purchaseCost: 120000,
      salvageValue: 20000,
      usefulLifeYears: 10,
      monthlyDepreciation: 833,
      accumulatedDepreciation: 22491,
      currentBookValue: 97509,
      runningHours: 980,
      hourlyOperatingRate: 40,
      serialNumber: 'SCH-36B-5510',
      assignedPerson: 'انجنیر حمید پمپ‌چی',
      status: 'active',
      notes: 'نل و بست‌های خروجی در وضعیت عالی',
      createdAt: new Date().toISOString()
    },
    {
      id: 'ast-3',
      assetTag: 'AST-103',
      name: 'دیزل جنراتور ۲۵۰ کاوا کامینز سایلنت',
      category: 'equipment',
      assignedProjectId: 'p1',
      purchaseDate: '2024-08-01',
      purchaseCost: 22000,
      salvageValue: 4000,
      usefulLifeYears: 6,
      monthlyDepreciation: 250,
      accumulatedDepreciation: 6250,
      currentBookValue: 15750,
      runningHours: 2150,
      hourlyOperatingRate: 15,
      serialNumber: 'CUM-250S-8812',
      assignedPerson: 'مسئول برق کارگاه',
      status: 'active',
      notes: 'تأمین برق اضطراری و موتور جوش‌ها',
      createdAt: new Date().toISOString()
    },
    {
      id: 'ast-4',
      assetTag: 'AST-104',
      name: 'لفت و بالابر مصالح ۱ تنی کارگاهی',
      category: 'equipment',
      assignedProjectId: 'p1',
      purchaseDate: '2025-01-10',
      purchaseCost: 7500,
      salvageValue: 1000,
      usefulLifeYears: 5,
      monthlyDepreciation: 108,
      accumulatedDepreciation: 2160,
      currentBookValue: 5340,
      runningHours: 3200,
      hourlyOperatingRate: 8,
      serialNumber: 'HST-1T-4029',
      assignedPerson: 'سرپرست کارگاه',
      status: 'maintenance',
      notes: 'تعویض لنت ترمز و میکروسوئیچ ایمنی طبقات',
      createdAt: new Date().toISOString()
    }
  ];

  const [assets, setAssets] = useState<FixedAsset[]>(() => loadFromStorage('assets', initialAssets));
  const [laborRecords, setLaborRecords] = useState<LaborRecord[]>(() => loadFromStorage('labor', initialLaborRecords));
  const [payments, setPayments] = useState<Payment[]>(() => loadFromStorage('payments', initialPayments));
  const [documents, setDocuments] = useState<DocumentRecord[]>(() => loadFromStorage('documents', initialDocuments));
  const [auditLogs, setAuditLogs] = useState<AuditLog[]>(() => loadFromStorage('auditLogs', initialAuditLogs));
  const [activityEvents, setActivityEvents] = useState<UserActivityEvent[]>(() => loadFromStorage('activityEvents', []));
  const [creditNotes,setCreditNotes]=useState<CreditNote[]>(()=>loadFromStorage('creditNotes',[]));
  const creditGuard=(row:any,action:string)=>{if(!transferAccess(currentUser,row.projectId,'credits',action,appSettings)||!transferAccess(currentUser,row.projectId,row.sourceCollection==='expenses'?'expenses':row.sourceCollection,'view',appSettings))throw new Error('Permission denied');};
  const addCreditNote=(data:any)=>{creditGuard(data,'create');setCreditNotes(rows=>[...rows,{...data,id:'credit-'+crypto.randomUUID(),createdAt:new Date().toISOString(),approvalStatus:'draft',approvalHistory:[]}]);};
  const updateCreditNote=(id:string,data:any)=>{const old=creditNotes.find(r=>r.id===id);if(!old)return;creditGuard(old,'edit');creditGuard(data,'edit');if(old.approvalStatus!=='draft')throw new Error('Locked');setCreditNotes(rows=>rows.map(r=>r.id===id?{...r,...data}:r));};
  const deleteCreditNote=(id:string)=>{const old=creditNotes.find(r=>r.id===id);if(!old)return;creditGuard(old,'delete');if(old.approvalStatus!=='draft')throw new Error('Locked');setCreditNotes(rows=>rows.filter(r=>r.id!==id));};
  useEffect(()=>{saveToStorage('creditNotes',creditNotes);},[creditNotes]);
  const [projectTransfers,setProjectTransfers]=useState<ProjectLedgerTransfer[]>(()=>loadFromStorage('projectTransfers',[]));
  const checkTransferAccess=(id:string,module:string,action:string)=>{if(!transferAccess(currentUser,id,module,action,appSettings))throw new Error('Permission denied');};
  const transferGuard=(r:any,action:string)=>{for(const id of [r.projectId,...(r.kind==='ownership_transfer'?[]:[r.toProjectId])])checkTransferAccess(id,'transfers',action);if(r.kind==='ownership_transfer')checkTransferAccess(r.projectId,'projects','edit');};
  const addProjectTransfer=(data:any)=>{transferGuard(data,'create');setProjectTransfers(rows=>[...rows,{...data,id:'transfer-'+crypto.randomUUID(),createdAt:new Date().toISOString(),approvalStatus:'draft',approvalHistory:[]}]);};
  const updateProjectTransfer=(id:string,data:any)=>{const old=projectTransfers.find(r=>r.id===id);if(!old)return;transferGuard(old,'edit');transferGuard(data,'edit');if(old.approvalStatus!=='draft')throw new Error('Locked');setProjectTransfers(rows=>rows.map(r=>r.id===id?{...r,...data}:r));};
  const deleteProjectTransfer=(id:string)=>{const old=projectTransfers.find(r=>r.id===id);if(!old)return;transferGuard(old,'delete');if(old.approvalStatus!=='draft')throw new Error('Locked');setProjectTransfers(rows=>rows.filter(r=>r.id!==id));};
  useEffect(()=>{saveToStorage('projectTransfers',projectTransfers);},[projectTransfers]);
  const [projectPartners, setProjectPartners] = useState<ProjectPartner[]>(() => loadFromStorage('projectPartners', initialProjectPartners));
  const [projectInvestments, setProjectInvestments] = useState<ProjectInvestment[]>(() => loadFromStorage('projectInvestments', initialProjectInvestments));
  const [partnerEquityTransfers, setPartnerEquityTransfers] = useState<PartnerEquityTransfer[]>(() => loadFromStorage('partnerEquityTransfers', []));
  const [projectBudgets, setProjectBudgets] = useState<ProjectBudget[]>(() => loadFromStorage('projectBudgets', initialProjectBudgets));
  const [notifications, setNotifications] = useState<SystemReminderNotification[]>(() => loadFromStorage('notifications', []));
  const [isNotificationCenterOpen, setIsNotificationCenterOpen] = useState(false);
  const [selectedNotification, setSelectedNotification] = useState<SystemReminderNotification | null>(null);

  // Google Drive state (in-memory token cached in googleDriveAuth)
  const [isGoogleDriveConnected, setIsGoogleDriveConnected] = useState<boolean>(false);
  const [googleDriveUserEmail, setGoogleDriveUserEmail] = useState<string | null>(null);

  // Initialize the independent Google Drive connection
  useEffect(() => {
    if (IS_PREVIEW) return;
    const unsubscribe = initAuth(
      (user, token) => {
        setIsGoogleDriveConnected(true);
        setGoogleDriveUserEmail(user.email);
      },
      () => {
        setIsGoogleDriveConnected(false);
        setGoogleDriveUserEmail(null);
      }
    );
    return () => unsubscribe();
  }, []);

  const connectGoogleDrive = async (): Promise<boolean> => {
    if (IS_PREVIEW) return false;
    try {
      const res = await googleSignIn();
      if (res) {
        setIsGoogleDriveConnected(true);
        setGoogleDriveUserEmail(res.user.email);
        logAudit('login', 'user', res.user.uid, `Connected Google Drive (${res.user.email})`, 'Google Drive OAuth Authorized');
        return true;
      }
      return false;
    } catch (err: any) {
      console.error('Google Drive connection failed:', err);
      return false;
    }
  };

  const disconnectGoogleDrive = async (): Promise<void> => {
    await googleSignOut();
    setIsGoogleDriveConnected(false);
    setGoogleDriveUserEmail(null);
    logAudit('update', 'user', 'gdrive', 'Disconnected Google Drive', 'User signed out from Google Drive');
  };

  // Sync to local storage
  useEffect(() => { saveToStorage('users', users); }, [users]);
  useEffect(() => { saveToStorage('projects', projects); }, [projects]);
  useEffect(() => { saveToStorage('currentProjId', currentProjectId); }, [currentProjectId]);
  useEffect(() => { saveToStorage('expenses', expenses); }, [expenses]);
  useEffect(() => { saveToStorage('steel', steelRecords); }, [steelRecords]);
  useEffect(() => { saveToStorage('concrete', concreteRecords); }, [concreteRecords]);
  useEffect(() => { saveToStorage('contractors', contractors); }, [contractors]);
  useEffect(() => { saveToStorage('suppliers', suppliers); }, [suppliers]);
  useEffect(() => { saveToStorage('labor', laborRecords); }, [laborRecords]);
  useEffect(() => { saveToStorage('assets', assets); }, [assets]);
  useEffect(() => { saveToStorage('apartments', apartments); }, [apartments]);
  useEffect(() => { saveToStorage('payments', payments); }, [payments]);
  useEffect(() => { saveToStorage('documents', documents); }, [documents]);
  useEffect(() => { saveToStorage('auditLogs', auditLogs); }, [auditLogs]);
  useEffect(() => { saveToStorage('activityEvents', activityEvents.slice(-600)); }, [activityEvents]);
  useEffect(() => { saveToStorage('projectPartners', projectPartners); }, [projectPartners]);
  useEffect(() => { saveToStorage('projectInvestments', projectInvestments); }, [projectInvestments]);
  useEffect(() => { saveToStorage('partnerEquityTransfers', partnerEquityTransfers); }, [partnerEquityTransfers]);
  useEffect(() => { saveToStorage('projectBudgets', projectBudgets); }, [projectBudgets]);
  useEffect(() => { saveToStorage('notifications', notifications); }, [notifications]);

  const isCurrentTimeDay = (): boolean => {
    const hour = new Date().getHours();
    return hour >= 7 && hour < 19;
  };

  const defaultAppSettings: AppSettings = {
    companyName: 'NIK Smart Count',
    companySubtitle: 'Construction finance and project management',
    logoUrl: '/assets/logo.png',
    receiptHeader: 'Official payment and receipt',
    receiptFooter: 'Valid with authorized approval and supporting documents.',
    receiptContact: '',
    theme: 'glassmorphism',
    darkMode: !isCurrentTimeDay(),
    themeSchedule: 'light',
    backgroundImageUrl: '',
    backgroundOpacity: 12,
    customCategories: [],
    aiEnabled: true,
    googleDrive: {
      connected: false,
    },
    reportCustomization: {
      style: 'classic',
      showLogo: true,
      showSignatures: true,
      signature1Title: 'Accountant',
      signature2Title: 'Site Engineer',
      signature3Title: 'Company Owner',
      headerNote: 'Construction expense and revenue report',
      footerNote: 'Review amounts against supporting documents and the accounting books.',
      watermarkText: '',
    }
  };

  const [appSettings, setAppSettings] = useState<AppSettings>(() => {
    const loaded = loadFromStorage<AppSettings>('appSettings', defaultAppSettings);
    const validThemes = ['glassmorphism', 'neumorphism', 'skeuomorphism', 'squirclemorphism', 'metalmorphism', 'ar_morphism'];
    const currentSchedule = loaded.themeSchedule || 'auto';
    const computedDark = currentSchedule === 'auto' ? !isCurrentTimeDay() : (loaded.darkMode ?? false);
    return {
      ...defaultAppSettings,
      ...loaded,
      theme: validThemes.includes(loaded.theme) ? loaded.theme : 'glassmorphism',
      logoUrl: loaded.logoUrl || '/assets/logo.png',
      darkMode: computedDark,
      themeSchedule: currentSchedule,
      reportCustomization: loaded.reportCustomization || defaultAppSettings.reportCustomization,
    };
  });

  // Persist app settings (theme, dark mode, branding, rates, ...) so the
  // anti-FOUC script in index.html and the next visit can restore them.
  useEffect(() => { saveToStorage('appSettings', appSettings); }, [appSettings]);

  const personalAppearance=usePersonalAppearance(currentUser?.id,appSettings);
  useEffect(()=>{document.documentElement.classList.toggle('dark',personalAppearance.dark);},[personalAppearance.dark]);

  const [searchQuery, setSearchQuery] = useState('');
  const [cameraModalOpen, setCameraModalOpen] = useState(false);
  const [cameraTargetData, setCameraTargetData] = useState<{ title?: string; relatedType?: string; relatedId?: string; amount?: number } | null>(null);

  const updateAppSettings = (updates: Partial<AppSettings>) => {
    setAppSettings(prev => ({ ...prev, ...updates }));
    logAudit('update', 'user', 'settings', 'App Settings & Branding', 'Updated system branding, theme or wallpaper');
  };

  const setThemeSchedule = (mode:'auto'|'light'|'dark') => personalAppearance.change({mode});

  useEffect(() => {
    document.documentElement.lang = language;
    document.title='NSC · '+designText(appSettings,language,'pageTitle');
    document.querySelector('meta[name=description]')?.setAttribute('content',designText(appSettings,language,'subtitle'));
    if (language === 'fa' || language === 'ps') {
      document.documentElement.dir = 'rtl';
    } else {
      document.documentElement.dir = 'ltr';
    }
  }, [language,appSettings]);

  const setLanguage = (lang: Language) => {
    setLanguageState(lang);
    saveToStorage('lang', lang);
    if(!IS_PREVIEW&&currentUser){try{localStorage.setItem('nsc_ui_language:'+currentUser.id,lang);}catch{}}
    if(currentUser && currentUser.customEnabledModules?.settings!==false && !(appSettings as any).dashboardDesign?.moduleSettings?.settings?.locked && !['view','edit'].some(action=>(appSettings as any).dashboardDesign?.moduleSettings?.settings?.disabledActions?.includes(action)) && (!currentUser.isMasterSuperAdmin && currentUser.role==='admin'&&!currentUser.ownerAdminId))setAppSettings(prev=>({...prev,language:lang} as AppSettings));
  };

  const currentProject = useMemo(() => {
    return projects.find(p => p.id === currentProjectId) || projects[0] || null;
  }, [projects, currentProjectId]);

  const setCurrentProjectId = (id: string) => {
    setCurrentProjectIdState(id);
  };

  const logAudit = (
    action: 'create' | 'update' | 'delete' | 'login' | string,
    entityType: AuditLog['entityType'],
    entityId: string,
    recordTitle: string,
    details: string,
    previousValue?: string,
    newValue?: string,
    projId?: string
  ) => {
    const now = new Date();
    const targetProj = projects.find(p => p.id === (projId || currentProjectId));
    const newLog: AuditLog = {
      id: 'aud-' + Date.now() + '-' + Math.random().toString(36).substring(2, 6),
      projectId: projId || currentProjectId,
      projectName: targetProj?.name || 'Project',
      userId: currentUser?.id || 'unknown',
      userName: currentUser?.name || 'System User',
      date: now.toISOString().split('T')[0],
      time: now.toTimeString().split(' ')[0],
      action,
      entityType,
      entityId,
      recordTitle,
      previousValue,
      newValue,
      details,
    };
    setAuditLogs(prev => [newLog, ...prev]);
  };

  const recordActivity = (user: User, kind: UserActivityEvent['kind'], feature?: string, label?: string) => {
    const ev: UserActivityEvent = {
      id: 'act-' + Date.now() + '-' + Math.random().toString(36).slice(2, 7),
      userId: user.id,
      userName: user.name,
      role: user.role,
      kind,
      feature,
      label,
      timestamp: new Date().toISOString(),
    };
    setActivityEvents(prev => [...prev.slice(-599), ev]);
  };

  useEffect(() => {
    if(IS_PREVIEW) return;
    localStorage.removeItem('nsc_auth_token');localStorage.removeItem('nsc_office7_key');
    for(const key of Object.keys(localStorage)) if(key.startsWith('nik_smart_count_'))localStorage.removeItem(key);
    backendApi.getCurrentUser().then(res=>{const user=res.data?.user;setCurrentUser(user||null);setUsers(user?[user]:[]);}).catch(()=>setCurrentUser(null));
    const expire=(e:Event)=>{if((e as CustomEvent).detail?.status===401||['licenseExpired','accountLocked','companyLocked'].includes((e as CustomEvent).detail?.code))setCurrentUser(null);};
    window.addEventListener('nsc-api-error',expire);return()=>window.removeEventListener('nsc-api-error',expire);
  },[]);

  const login = async (usernameOrEmail:string,password?:string,loginType?:'admin'|'employee'):Promise<{success:boolean;error?:string;code?:string}> => {
    if(!password) return {success:false,error:'A password is required.'};
    try {
      let user:User;
      if(IS_PREVIEW) {
        const found=users.find(u=>u.username.toLowerCase()===usernameOrEmail.trim().toLowerCase()||u.email.toLowerCase()===usernameOrEmail.trim().toLowerCase());
        if(!found||found.password!==password||!found.active||found.isLockedBySuperAdmin)return {success:false,error:'Username or password is incorrect.'};
        const license=previewLicenseUsers(users).find(u=>u.id===found.id);if(license?.licenseStatus!=='active')return {success:false,code:license?.licenseStatus==='expired'?'licenseExpired':license?.licenseStatus==='companyLocked'?'companyLocked':'accountLocked'};
        user=found;
      } else {
        const res=await backendApi.login(usernameOrEmail.trim(),password);
        if(!res.data?.user)return {success:false,error:'Sign in failed.'};
        user=res.data.user;setUsers([user]);
      }
      setCurrentUser(user);saveToStorage('currentUser',user);
      setAppSettings(prev=>({...prev,companyName:user.companyName||prev.companyName}));
      return {success:true};
    } catch(e:any){return {success:false,error:e.message,code:e.code};}
  };

  const requestPasswordReset = async (email: string): Promise<{ success: boolean; otpCode?: string; error?: string }> => {
    if (!IS_PREVIEW) { try { await backendApi.requestPasswordReset(email); return {success:true}; } catch(e:any) {return {success:false,error:e.message};} }
    const user = users.find(u => u.email.toLowerCase() === email.trim().toLowerCase());
    if (!user) {
      return { success: false, error: 'حسابی با این ایمیل یافت نشد' };
    }
    const resetOtp = Math.floor(100000 + Math.random() * 900000).toString();
    setUsers(prev => prev.map(u => u.id === user.id ? { ...u, otpCode: resetOtp } : u));
    logAudit('update', 'user', user.id, `Password reset requested for ${user.email}`, `Reset OTP issued`);
    return { success: true, otpCode: resetOtp };
  };

  const completePasswordReset = async (email: string, otpCode: string, newPassword: string): Promise<{ success: boolean; error?: string;code?:string }> => {
    if (!IS_PREVIEW) { try { await backendApi.completePasswordReset(otpCode,newPassword); return {success:true}; } catch(e:any) {return {success:false,error:e.message};} }
    const user = users.find(u => u.email.toLowerCase() === email.trim().toLowerCase());
    if (!user) {
      return { success: false, error: 'کاربر یافت نشد' };
    }
    if (!user.otpCode || user.otpCode !== otpCode.trim()) {
      return { success: false, error: 'کد تایید نادرست است' };
    }
    setUsers(prev => prev.map(u => u.id === user.id ? { ...u, password: newPassword, otpCode: undefined } : u));
    logAudit('update', 'user', user.id, `Password updated for ${user.email}`, 'Password changed successfully');
    return { success: true };
  };

  const toggleDarkMode = () => personalAppearance.change({mode:personalAppearance.dark?'light':'dark'});
  const setTheme = (theme:AppTheme) => personalAppearance.change({theme:theme as any});

  const logout = () => {
    if(blankTestMode)return;
    clearDriveToken();
    if(!IS_PREVIEW) backendApi.logout().catch(()=>{window.dispatchEvent(new CustomEvent('nsc-api-error',{detail:{message:'Server logout failed. Close this browser session and retry.'}}));});
    if (currentUser) {
      recordActivity(currentUser, 'logout');
    }
    setCurrentUser(null);
    saveToStorage('currentUser', null);
  };

  const addUser = (userData: Omit<User, 'id' | 'createdAt'>) => {
    const newUser: User = {
      ...userData,
      id: 'user-' + Date.now(),
      createdAt: new Date().toISOString(),
    };
    setUsers(prev => [...prev, newUser]);
    logAudit('create', 'user', newUser.id, `Created user ${newUser.name}`, `Role: ${newUser.role}`);
  };

  const updateUser = (id: string, updates: Partial<User>) => {
    setUsers(prev => prev.map(u => (u.id === id ? { ...u, ...updates } : u)));
    logAudit('update', 'user', id, `Updated user profile`, JSON.stringify(updates));
  };

  const toggleUserActive = (id: string) => {
    setUsers(prev => prev.map(u => {
      if (u.id === id) {
        const updated = { ...u, active: !u.active };
        logAudit('update', 'user', id, `${u.name} status changed`, `Active: ${updated.active}`);
        return updated;
      }
      return u;
    }));
  };

  // Projects CRUD
  const addProject = (projectData: Omit<Project, 'id' | 'createdAt'>) => {
    const newProject: Project = {
      ...projectData,
      id: crypto.randomUUID(),
      createdAt: new Date().toISOString(),
    };
    setProjects(prev => [...prev, newProject]);
    setCurrentProjectIdState(newProject.id);

    if (newProject.initialGeneralExpenses && newProject.initialGeneralExpenses.length > 0) {
      const createdExpenses: Expense[] = newProject.initialGeneralExpenses
        .filter(item => item.amount > 0)
        .map((item, idx) => {
          const exchangeRate = newProject.defaultExchangeRate || 70;
          const amountUSD = item.currency === 'USD' 
            ? item.amount 
            : (exchangeRate > 0 ? roundMoney((item.amount / exchangeRate)) : item.amount);
          const amountAFN = item.currency === 'AFN'
            ? item.amount
            : roundMoney(item.amount * exchangeRate);
          return {
            id: `exp-init-${Date.now()}-${idx}`,
            projectId: newProject.id,
            category: item.title,
            description: item.notes || item.title,
            date: todayISO(),
            time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false }),
            amount: item.amount,
            currency: item.currency,
            exchangeRate,
            amountInUSD: amountUSD,
            amountInAFN: amountAFN,
            paymentStatus: 'paid',
            paidAmount: item.amount,
            remainingAmount: 0,
            paymentMethod: 'Cash',
            createdAt: new Date().toISOString(),
          };
        });
      if (createdExpenses.length > 0) {
        createdExpenses.forEach(expense=>addExpense(expense));
      }
    }

    if (newProject.partners && newProject.partners.length > 0) {
      newProject.partners.forEach((part, pIdx) => {
        const pId = 'part-' + Date.now() + '-' + pIdx;
        const newPart: ProjectPartner = {
          ...part,
          id: pId,
          projectId: newProject.id,
          currency: part.currency || newProject.currency || 'AFN',
          createdAt: new Date().toISOString(),
        };
        setProjectPartners(prev => [...prev, newPart]);
        if (newPart.initialInvestment && newPart.initialInvestment > 0) {
          const newInv: ProjectInvestment = {
      approvalStatus: 'draft', approvalHistory: [],
            id: 'inv-' + Date.now() + '-' + pIdx,
            projectId: newProject.id,
            partnerId: pId,
            partnerName: newPart.name || 'شریک',
            amount: newPart.initialInvestment,
            currency: newPart.currency,
            exchangeRate: newProject.defaultExchangeRate || 70,
            date: newPart.investmentDate || todayISO(),
            type: 'initial',
            paymentMethod: 'Cash',
            notes: newPart.notes || '',
            createdAt: new Date().toISOString(),
          };
          setProjectInvestments(prev => [newInv, ...prev]);
        }
      });
    }
    logAudit('create', 'project', newProject.id, newProject.name, `New building project created: ${newProject.floors} floors, ${newProject.units} units`, undefined, undefined, newProject.id);
    if (IS_PREVIEW) backendApi.saveProject({
      id: newProject.id,
      name: newProject.name,
      contractValue: newProject.budget,
      approvedBudget: newProject.budget,
      currency: newProject.currency,
      location: newProject.address || newProject.city || '',
      startDate: newProject.startDate,
      expectedFinish: newProject.expectedCompletionDate,
      status: newProject.status,
    }).catch(() => {});
    return newProject;
  };

  const updateProject = (id: string, updates: Partial<Project>) => {
    const oldProj = projects.find(p => p.id === id);
    setProjects(prev => prev.map(p => (p.id === id ? { ...p, ...updates } : p)));
    logAudit('update', 'project', id, updates.name || oldProj?.name || 'Project', `Project details updated`, JSON.stringify(oldProj), JSON.stringify(updates), id);
  };

  const deleteProject = (id: string) => {
    const oldProj = projects.find(p => p.id === id);
    if([expenses,steelRecords,concreteRecords,contractors,suppliers,laborRecords,assets,apartments,payments,documents,projectPartners,projectInvestments,partnerEquityTransfers,projectBudgets,projectTransfers,creditNotes].some(rows=>rows.some((r:any)=>r.projectId===id||r.assignedProjectId===id||r.toProjectId===id)))throw Error('Project records must be preserved');
    setProjects(prev => prev.filter(p => p.id !== id));
    if (currentProjectId === id) {
      const remaining = projects.filter(p => p.id !== id);
      setCurrentProjectIdState(remaining[0]?.id||'');
    }
    setProjectPartners(prev => prev.filter(p => p.projectId !== id));
    setProjectInvestments(prev => prev.filter(i => i.projectId !== id));
    logAudit('delete', 'project', id, oldProj?.name || 'Project', 'Project removed from system');
  };

  // Partners & Investments Management
  const addProjectPartner = (partnerData: Omit<ProjectPartner, 'id' | 'createdAt'>): ProjectPartner => {
    const newPartner: ProjectPartner = {
      ...partnerData,
      id: 'part-' + Date.now() + '-' + Math.floor(Math.random() * 1000),
      createdAt: new Date().toISOString(),
    };
    setProjectPartners(prev => [...prev, newPartner]);

    if (newPartner.initialInvestment && newPartner.initialInvestment > 0) {
      const proj = projects.find(p => p.id === newPartner.projectId);
      const exRate = proj?.defaultExchangeRate || 70;
      const initialInv: ProjectInvestment = {
        approvalStatus:'draft',approvalHistory:[],
        id: 'inv-' + crypto.randomUUID(),
        projectId: newPartner.projectId,
        partnerId: newPartner.id,
        partnerName: newPartner.name || 'شریک',
        amount: newPartner.initialInvestment,
        currency: newPartner.currency || 'AFN',
        exchangeRate: exRate,
        date: newPartner.investmentDate || todayISO(),
        type: 'initial',
        paymentMethod: 'Cash',
        notes: newPartner.notes || '',
        createdAt: new Date().toISOString(),
      };
      setProjectInvestments(prev => [initialInv, ...prev]);
    }
    logAudit('create', 'project', newPartner.projectId, `Partner: ${newPartner.name}`, `Added partner with share: ${newPartner.sharePercentage || 0}%`, undefined, undefined, newPartner.projectId);
    return newPartner;
  };

  const updateProjectPartner = (id: string, updates: Partial<ProjectPartner>) => {
    const old = projectPartners.find(p => p.id === id);
    setProjectPartners(prev => prev.map(p => (p.id === id ? { ...p, ...updates } : p)));
    if (updates.name && old && updates.name !== old.name) {
      setProjectInvestments(prev => prev.map(inv => inv.partnerId === id&&inv.approvalStatus==='draft' ? { ...inv, partnerName: updates.name! } : inv));
    }
    logAudit('update', 'project', id, `Partner: ${updates.name || old?.name || id}`, 'Updated partner information', undefined, undefined, old?.projectId);
  };

  const deleteProjectPartner = (id: string) => {
    const old = projectPartners.find(p => p.id === id);
    if(projectInvestments.some(i=>i.partnerId===id)||partnerEquityTransfers.some(e=>e.fromPartnerId===id||e.toPartnerId===id))throw Error('Partner history must be preserved');
    setProjectPartners(prev => prev.filter(p => p.id !== id));
    setProjectInvestments(prev => prev.filter(inv => inv.partnerId !== id));
    logAudit('delete', 'project', id, `Partner: ${old?.name || id}`, 'Deleted partner and associated records', undefined, undefined, old?.projectId);
  };

  const addProjectInvestment = (invData: Omit<ProjectInvestment, 'id' | 'createdAt'>): ProjectInvestment => {
    const proj = projects.find(p => p.id === invData.projectId);
    const newInv: ProjectInvestment = {
      approvalStatus: 'draft', approvalHistory: [],
      ...invData,
      exchangeRate: invData.exchangeRate || proj?.defaultExchangeRate || 70,
      id: 'inv-' + crypto.randomUUID(),
      createdAt: new Date().toISOString(),
    };
    setProjectInvestments(prev => [newInv, ...prev]);
    logAudit('create', 'project', newInv.id, `Investment: ${newInv.partnerName}`, `Added ${newInv.type === 'initial' ? 'initial' : 'additional'} investment of ${newInv.amount.toLocaleString()} ${newInv.currency}`, undefined, undefined, newInv.projectId);
    return newInv;
  };

  const updateProjectInvestment = (id: string, updates: Partial<ProjectInvestment>) => {
    const old = projectInvestments.find(inv => inv.id === id);
    setProjectInvestments(prev => prev.map(inv => (inv.id === id ? { ...inv, ...updates } : inv)));
    logAudit('update', 'project', id, `Investment: ${old?.partnerName || id}`, `Updated investment transaction`, undefined, undefined, old?.projectId);
  };

  const deleteProjectInvestment = (id: string) => {
    const old = projectInvestments.find(inv => inv.id === id);
    setProjectInvestments(prev => prev.filter(inv => inv.id !== id));
    logAudit('delete', 'project', id, `Investment: ${old?.partnerName || id}`, `Deleted investment record of ${old?.amount} ${old?.currency}`, undefined, undefined, old?.projectId);
  };

  const transferPartnerEquity = (data: Omit<PartnerEquityTransfer, 'id' | 'createdAt'>, newBuyer?: {name:string;phone?:string}): PartnerEquityTransfer => {
    const buyer:ProjectPartner|undefined=newBuyer?{id:data.toPartnerId!,projectId:data.projectId,name:newBuyer.name.trim(),phone:newBuyer.phone,sharePercentage:0,initialInvestment:0,currency:data.currency,status:'active',createdAt:new Date().toISOString()}:undefined;
    if(buyer&&(!buyer.name||projectPartners.some(p=>p.id===buyer.id)))throw Error('Invalid buyer');
    const source=projectPartners.find(p=>p.id===data.fromPartnerId&&p.projectId===data.projectId),target=buyer||projectPartners.find(p=>p.id===data.toPartnerId&&p.projectId===data.projectId);
    if(!source||!target||source.id===target.id||!Number.isFinite(data.transferredPercentage)||data.transferredPercentage<=0||data.transferredPercentage>(source.sharePercentage||0)||!Number.isFinite(data.transferPrice)||data.transferPrice<0)throw Error('Invalid share transfer');
    const id = 'eq-tr-' + crypto.randomUUID();
    const newTransfer: PartnerEquityTransfer = {
      ...data,
      id,
      createdAt: new Date().toISOString()
    };
    setPartnerEquityTransfers(prev => [newTransfer, ...prev]);

    // Update seller partner
    setProjectPartners(prev => [...prev,...(buyer?[buyer]:[])].map(p => {
      if (p.id === data.fromPartnerId) {
        const remainingShare = Math.max(0, (p.sharePercentage || 0) - data.transferredPercentage);
        return {
          ...p,
          sharePercentage: Math.round(remainingShare*1e8)/1e8,
          status: remainingShare === 0 ? 'exited' : p.status,
          exitDate: remainingShare === 0 ? data.transferDate : p.exitDate,
          exitReason: remainingShare === 0 ? data.notes || '' : p.exitReason,
          transferredToPartnerId: data.toPartnerId,
          transferredToPartnerName: data.toPartnerName,
          transferPrice: data.transferPrice,
          transferCurrency: data.currency,
        };
      }
      // If buyer is an existing partner, increase their share
      if (data.toPartnerId && p.id === data.toPartnerId) {
        return {
          ...p,
          sharePercentage: Math.round(((p.sharePercentage || 0) + data.transferredPercentage)*1e8)/1e8,
          status:'active',exitDate:undefined,exitReason:undefined,
        };
      }
      return p;
    }));

    logAudit('create', 'project', data.projectId, data.fromPartnerName, `Transferred ${data.transferredPercentage}% from ${data.fromPartnerName} to ${data.toPartnerName}; private price ${data.transferPrice} ${data.currency}`, undefined, undefined, data.projectId);

    return newTransfer;
  };

  // Expenses CRUD
  const addExpense = (expenseData: Omit<Expense, 'id' | 'createdAt' | 'createdBy'>) => {
    const time = expenseData.time || new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
    const amount = roundMoney(Number(expenseData.amount || 0));
    const paidAmount = expenseData.paidAmount !== undefined ? roundMoney(Number(expenseData.paidAmount)) : 0;
    const remainingAmount = Math.max(0, roundMoney((amount - paidAmount)));
    const paymentStatus: PaymentStatus = expenseData.paymentStatus || (paidAmount >= amount ? 'paid' : (paidAmount > 0 ? 'partial' : 'unpaid'));
    const proj = projects.find(p => p.id === expenseData.projectId);
    const exRate = expenseData.exchangeRate || proj?.defaultExchangeRate || 70;
    const currency = expenseData.currency || 'USD';
    const amountInUSD = currency === 'USD' ? amount : (exRate > 0 ? roundMoney((amount / exRate)) : amount);
    const amountInAFN = currency === 'AFN' ? amount : roundMoney(amount * exRate);

    const newExpense: Expense = {
      approvalStatus: 'draft', approvalHistory: [],
      ...expenseData,
      time,
      amount,
      paidAmount,
      remainingAmount,
      paymentStatus,
      currency,
      exchangeRate: exRate,
      amountInUSD,
      amountInAFN,
      id: crypto.randomUUID(),
      createdAt: new Date().toISOString(),
      createdBy: currentUser?.name || 'Admin',
    };
    setExpenses(prev => [newExpense, ...prev]);

    if (paidAmount > 0) {
      const paidInUSD = currency === 'USD' ? paidAmount : (exRate > 0 ? roundMoney((paidAmount / exRate)) : paidAmount);
      const paidInAFN = currency === 'AFN' ? paidAmount : roundMoney(paidAmount * exRate);
      const newPay: Payment = {
      approvalStatus: 'draft', approvalHistory: [],
        id: crypto.randomUUID(),
        projectId: newExpense.projectId,
        date: newExpense.date,
        time: newExpense.time || time,
        receiptNumber: newExpense.billNumber || `EXP-${Date.now().toString().slice(-4)}`,
        partyName: newExpense.partyName || '',
        amount: paidAmount,
        currency,
        exchangeRate: exRate,
        amountInUSD: paidInUSD,
        amountInAFN: paidInAFN,
        method: (newExpense.paymentMethod as any) || 'Cash',
        description: newExpense.item || newExpense.description,
        relatedId: newExpense.id,
        relatedType: 'expense',
        createdAt: new Date().toISOString(),
        createdBy: currentUser?.name || 'Admin',
      };
      setPayments(prev => [newPay, ...prev]);
    }

    if (!newExpense.approvalStatus && newExpense.partyName && newExpense.partyName.trim()) {
      setSuppliers(prev => {
        const existing = prev.find(s => s.projectId === newExpense.projectId && s.name.trim().toLowerCase() === newExpense.partyName!.trim().toLowerCase());
        if (existing) {
          const purchases = (existing.totalPurchases || 0) + amountInUSD;
          const paid = (existing.totalPaid || 0) + (paidAmount > 0 ? (currency === 'USD' ? paidAmount : (exRate > 0 ? paidAmount / exRate : paidAmount)) : 0);
          return prev.map(s => s.id === existing.id ? { ...s, totalPurchases: purchases, remainingBalance: Math.max(0, purchases - paid) } : s);
        } else {
          const purchases = amountInUSD;
          const paid = paidAmount > 0 ? (currency === 'USD' ? paidAmount : (exRate > 0 ? paidAmount / exRate : paidAmount)) : 0;
          const newSupp: Supplier = {
            id: 'supp-' + Date.now(),
            projectId: newExpense.projectId,
            name: newExpense.partyName!.trim(),
            phone: '',
            materialsSupplied: newExpense.item || newExpense.category,
            totalPurchases: purchases,
            totalPaid: paid,
            remainingBalance: Math.max(0, purchases - paid),
            createdAt: new Date().toISOString(),
          };
          return [...prev, newSupp];
        }
      });
    }
    logAudit('create', 'expense', newExpense.id, `${newExpense.category}: ${newExpense.description}`, `Amount: $${newExpense.amount.toLocaleString()} - Status: ${newExpense.paymentStatus}`, undefined, `$${newExpense.amount}`, newExpense.projectId);
    if (IS_PREVIEW) backendApi.createPayment({
      projectId: newExpense.projectId,
      amount: newExpense.amount,
      currency: newExpense.currency,
      exchangeRate: newExpense.exchangeRate,
      payeeName: newExpense.partyName || newExpense.category,
      expenseCategory: newExpense.category,
      description: newExpense.description || newExpense.item,
      paymentMethod: (newExpense.paymentMethod || 'cash').toLowerCase(),
      date: newExpense.date,
    }).catch(() => {});
  };

  const updateExpense = (id: string, updates: Partial<Expense>) => {
    const old = expenses.find(e => e.id === id);
    if (!old) return;
    const amount = updates.amount !== undefined ? roundMoney(Number(updates.amount)) : old.amount;
    const paidAmount = updates.paidAmount !== undefined ? roundMoney(Number(updates.paidAmount)) : (old.paidAmount ?? amount);
    const remainingAmount = Math.max(0, roundMoney((amount - paidAmount)));
    const paymentStatus: PaymentStatus = updates.paymentStatus || (paidAmount >= amount ? 'paid' : (paidAmount > 0 ? 'partial' : 'unpaid'));
    setExpenses(prev => prev.map(e => (e.id === id ? { 
      ...e, 
      ...updates,
      amount,
      paidAmount,
      remainingAmount,
      paymentStatus
    } : e)));

    if (updates.paidAmount !== undefined || updates.description !== undefined) {
      setPayments(prev => prev.map(p => {
        if (p.relatedType === 'expense' && p.relatedId === id) {
          const proj = projects.find(pr => pr.id === (updates.projectId || old.projectId));
          const exRate = p.exchangeRate || proj?.defaultExchangeRate || 70;
          const newAmt = updates.paidAmount !== undefined ? updates.paidAmount : p.amount;
          return {
            ...p,
            amount: newAmt,
            amountInUSD: p.currency === 'USD' ? newAmt : (exRate > 0 ? roundMoney((newAmt / exRate)) : newAmt),
            amountInAFN: p.currency === 'AFN' ? newAmt : roundMoney(newAmt * exRate),
            description: updates.description || p.description,
          };
        }
        return p;
      }));
    }
    logAudit('update', 'expense', id, updates.description || old.description || 'Expense', `Updated expense record`, `$${old.amount}`, `$${amount}`, old.projectId);
  };

  const deleteExpense = (id: string) => {
    const old = expenses.find(e => e.id === id);
    setExpenses(prev => prev.filter(e => e.id !== id));
    setPayments(prev => prev.filter(p => !(p.relatedType === 'expense' && p.relatedId === id)));
    logAudit('delete', 'expense', id, old?.description || 'Expense', `Deleted expense of $${old?.amount}`, `$${old?.amount}`, undefined, old?.projectId);
  };

  // Steel CRUD
  const addSteelRecord = (data: Omit<SteelRecord, 'id' | 'tons' | 'totalAmount' | 'remainingBalance' | 'createdAt'>) => {
    const tons = Number((data.kg / 1000).toFixed(3));
    const totalAmount = roundMoney((tons * data.pricePerTon));
    const paidAmount = roundMoney(Number(data.paidAmount || 0));
    const remainingBalance = Math.max(0, roundMoney((totalAmount - paidAmount)));
    const time = (data as any).time || new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
    const proj = projects.find(p => p.id === data.projectId);
    const exRate = (data as any).exchangeRate || proj?.defaultExchangeRate || 70;
    const currency = data.currency || 'USD';
    const newRecord: SteelRecord = {
      approvalStatus: 'draft', approvalHistory: [],
      currency: 'USD',
      time,
      ...data,
      id: 'stl-' + Date.now(),
      tons,
      totalAmount,
      paidAmount,
      remainingBalance,
      exchangeRate: exRate,
      amountInUSD: currency === 'USD' ? totalAmount : roundMoney((totalAmount / exRate)),
      amountInAFN: currency === 'AFN' ? totalAmount : roundMoney((totalAmount * exRate)),
      createdAt: new Date().toISOString(),
    };
    setSteelRecords(prev => [newRecord, ...prev]);

    if (paidAmount > 0) {
      const newPay: Payment = {
      approvalStatus: 'draft', approvalHistory: [],
        id: 'pay-stl-' + Date.now(),
        projectId: newRecord.projectId,
        date: newRecord.date,
        time: newRecord.time || time,
        receiptNumber: newRecord.billNumber,
        partyName: newRecord.supplierName,
        amount: paidAmount,
        currency,
        exchangeRate: exRate,
        amountInUSD: currency === 'USD' ? paidAmount : roundMoney((paidAmount / exRate)),
        amountInAFN: currency === 'AFN' ? paidAmount : roundMoney((paidAmount * exRate)),
        method: 'Cash',
        description: [newRecord.billNumber,newRecord.supplierName].filter(Boolean).join(' · '),
        relatedId: newRecord.id,
        relatedType: 'material_steel',
        createdAt: new Date().toISOString(),
        createdBy: currentUser?.name || 'Admin',
      };
      setPayments(prev => [newPay, ...prev]);
    }

    if (!newRecord.approvalStatus && newRecord.supplierName.trim()) {
      setSuppliers(prev => {
        const existing = prev.find(s => s.projectId === newRecord.projectId && s.name.trim().toLowerCase() === newRecord.supplierName.trim().toLowerCase());
        if (existing) {
          return prev;
        } else {
          const newSupp: Supplier = {
            id: 'supp-' + Date.now(),
            projectId: newRecord.projectId,
            name: newRecord.supplierName.trim(),
            phone: '',
            materialsSupplied: 'سیخ‌گول / فولاد',
            totalPurchases: totalAmount,
            totalPaid: paidAmount,
            remainingBalance,
            createdAt: new Date().toISOString(),
          };
          return [...prev, newSupp];
        }
      });
    }
    logAudit('create', 'steel', newRecord.id, `Steel Bill #${newRecord.billNumber} (${newRecord.supplierName})`, `${newRecord.kg} KG (${newRecord.tons} Tons) @ $${newRecord.pricePerTon}/Ton = $${newRecord.totalAmount.toLocaleString()}`, undefined, `$${newRecord.totalAmount}`, newRecord.projectId);
  };

  const updateSteelRecord = (id: string, updates: Partial<SteelRecord>) => {
    const old = steelRecords.find(s => s.id === id);
    if (!old) return;
    const currency = updates.currency || old.currency || 'USD';
    const kg = updates.kg !== undefined ? updates.kg : old.kg;
    const pricePerTon = updates.pricePerTon !== undefined ? updates.pricePerTon : old.pricePerTon;
    const paidAmount = updates.paidAmount !== undefined ? roundMoney(Number(updates.paidAmount)) : old.paidAmount;
    const tons = Number((kg / 1000).toFixed(3));
    const totalAmount = roundMoney((tons * pricePerTon));
    const remainingBalance = Math.max(0, roundMoney((totalAmount - paidAmount)));
    setSteelRecords(prev => prev.map(s => (s.id === id ? { 
      ...s, 
      ...updates, 
      kg, 
      pricePerTon, 
      tons, 
      totalAmount, 
      paidAmount, 
      remainingBalance 
    } : s)));
    logAudit('update', 'steel', id, `Steel Bill #${updates.billNumber || old.billNumber}`, `Updated steel record`, `$${old.totalAmount}`, `$${totalAmount}`, old.projectId);
  };

  const deleteSteelRecord = (id: string) => {
    const old = steelRecords.find(s => s.id === id);
    setSteelRecords(prev => prev.filter(s => s.id !== id));
    setPayments(prev => prev.filter(p => !(p.relatedType === 'material_steel' && p.relatedId === id)));
    logAudit('delete', 'steel', id, `Steel Bill #${old?.billNumber}`, `Deleted steel record of $${old?.totalAmount}`, `$${old?.totalAmount}`, undefined, old?.projectId);
  };

  // Concrete CRUD
  const addConcreteRecord = (data: Omit<ConcreteRecord, 'id' | 'totalAmount' | 'remainingBalance' | 'createdAt'>) => {
    const totalAmount = roundMoney(((data.quantityM3 * data.pricePerM3) + (data.pumpCharge || 0)));
    const paidAmount = roundMoney(Number(data.paidAmount || 0));
    const remainingBalance = Math.max(0, roundMoney((totalAmount - paidAmount)));
    const time = (data as any).time || new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
    const proj = projects.find(p => p.id === data.projectId);
    const exRate = (data as any).exchangeRate || proj?.defaultExchangeRate || 70;
    const currency = data.currency || 'USD';
    const newRecord: ConcreteRecord = {
      approvalStatus: 'draft', approvalHistory: [],
      currency: 'USD',
      time,
      ...data,
      id: 'cnc-' + Date.now(),
      totalAmount,
      paidAmount,
      remainingBalance,
      exchangeRate: exRate,
      amountInUSD: currency === 'USD' ? totalAmount : roundMoney((totalAmount / exRate)),
      amountInAFN: currency === 'AFN' ? totalAmount : roundMoney((totalAmount * exRate)),
      createdAt: new Date().toISOString(),
    };
    setConcreteRecords(prev => [newRecord, ...prev]);

    if (paidAmount > 0) {
      const newPay: Payment = {
      approvalStatus: 'draft', approvalHistory: [],
        id: 'pay-cnc-' + Date.now(),
        projectId: newRecord.projectId,
        date: newRecord.date,
        time: newRecord.time || time,
        receiptNumber: newRecord.billNumber,
        partyName: newRecord.supplierName,
        amount: paidAmount,
        currency,
        exchangeRate: exRate,
        amountInUSD: currency === 'USD' ? paidAmount : roundMoney((paidAmount / exRate)),
        amountInAFN: currency === 'AFN' ? paidAmount : roundMoney((paidAmount * exRate)),
        method: 'Cash',
        description: [newRecord.billNumber,newRecord.supplierName].filter(Boolean).join(' · '),
        relatedId: newRecord.id,
        relatedType: 'material_concrete',
        createdAt: new Date().toISOString(),
        createdBy: currentUser?.name || 'Admin',
      };
      setPayments(prev => [newPay, ...prev]);
    }

    if (!newRecord.approvalStatus && newRecord.supplierName.trim()) {
      setSuppliers(prev => {
        const existing = prev.find(s => s.projectId === newRecord.projectId && s.name.trim().toLowerCase() === newRecord.supplierName.trim().toLowerCase());
        if (existing) {
          return prev;
        } else {
          const newSupp: Supplier = {
            id: 'supp-' + Date.now(),
            projectId: newRecord.projectId,
            name: newRecord.supplierName.trim(),
            phone: '',
            materialsSupplied: 'کانکریت آماده و پمپ',
            totalPurchases: totalAmount,
            totalPaid: paidAmount,
            remainingBalance,
            createdAt: new Date().toISOString(),
          };
          return [...prev, newSupp];
        }
      });
    }
    logAudit('create', 'concrete', newRecord.id, `Concrete Bill #${newRecord.billNumber} (${newRecord.supplierName})`, `${newRecord.quantityM3} m³ @ $${newRecord.pricePerM3}/m³ = $${newRecord.totalAmount.toLocaleString()}`, undefined, `$${newRecord.totalAmount}`, newRecord.projectId);
  };

  const updateConcreteRecord = (id: string, updates: Partial<ConcreteRecord>) => {
    const old = concreteRecords.find(c => c.id === id);
    if (!old) return;
    const currency = updates.currency || old.currency || 'USD';
    const quantityM3 = updates.quantityM3 !== undefined ? updates.quantityM3 : old.quantityM3;
    const pricePerM3 = updates.pricePerM3 !== undefined ? updates.pricePerM3 : old.pricePerM3;
    const pumpCharge = updates.pumpCharge !== undefined ? updates.pumpCharge : (old.pumpCharge || 0);
    const paidAmount = updates.paidAmount !== undefined ? roundMoney(Number(updates.paidAmount)) : old.paidAmount;
    const totalAmount = roundMoney(((quantityM3 * pricePerM3) + pumpCharge));
    const remainingBalance = Math.max(0, roundMoney((totalAmount - paidAmount)));
    setConcreteRecords(prev => prev.map(c => (c.id === id ? {
      ...c,
      ...updates,
      quantityM3,
      pricePerM3,
      pumpCharge,
      totalAmount,
      paidAmount,
      remainingBalance
    } : c)));
    logAudit('update', 'concrete', id, `Concrete Bill #${updates.billNumber || old.billNumber}`, `Updated concrete pour record`, `$${old.totalAmount}`, `$${totalAmount}`, old.projectId);
  };

  const deleteConcreteRecord = (id: string) => {
    const old = concreteRecords.find(c => c.id === id);
    setConcreteRecords(prev => prev.filter(c => c.id !== id));
    setPayments(prev => prev.filter(p => !(p.relatedType === 'material_concrete' && p.relatedId === id)));
    logAudit('delete', 'concrete', id, `Concrete Bill #${old?.billNumber}`, `Deleted concrete record of $${old?.totalAmount}`, `$${old?.totalAmount}`, undefined, old?.projectId);
  };

  // Contractors CRUD
  const addContractor = (data: Omit<Contractor, 'id' | 'createdAt'>) => {
    const newContractor: Contractor = {
      workDescription: '',
      ...data,
      id: 'cont-' + Date.now(),
      createdAt: new Date().toISOString(),
    };
    setContractors(prev => [...prev, newContractor]);
    logAudit('create', 'contractor', newContractor.id, newContractor.name, `New contractor added: ${newContractor.contractType}, Contract: $${newContractor.contractAmount.toLocaleString()}`, undefined, `$${newContractor.contractAmount}`, newContractor.projectId);
  };

  const updateContractor = (id: string, updates: Partial<Contractor>) => {
    const old = contractors.find(c => c.id === id);
    setContractors(prev => prev.map(c => (c.id === id ? { ...c, ...updates } : c)));
    logAudit('update', 'contractor', id, updates.name || old?.name || 'Contractor', `Updated contractor terms`, `$${old?.contractAmount}`, `$${updates.contractAmount ?? old?.contractAmount}`, old?.projectId);
  };

  const deleteContractor = (id: string) => {
    const old = contractors.find(c => c.id === id);
    setContractors(prev => prev.filter(c => c.id !== id));
    logAudit('delete', 'contractor', id, old?.name || 'Contractor', `Deleted contractor`, `$${old?.contractAmount}`, undefined, old?.projectId);
  };

  // Suppliers CRUD
  const addSupplier = (data: Omit<Supplier, 'id' | 'createdAt'>) => {
    const newSupplier: Supplier = {
      contact: '',
      ...data,
      id: 'supp-' + Date.now(),
      createdAt: new Date().toISOString(),
    };
    setSuppliers(prev => [...prev, newSupplier]);
    logAudit('create', 'supplier', newSupplier.id, newSupplier.name, `Registered supplier: ${newSupplier.materialsSupplied}`, undefined, undefined, newSupplier.projectId);
  };

  const updateSupplier = (id: string, updates: Partial<Supplier>) => {
    const old = suppliers.find(s => s.id === id);
    setSuppliers(prev => prev.map(s => (s.id === id ? { ...s, ...updates } : s)));
    logAudit('update', 'supplier', id, updates.name || old?.name || 'Supplier', `Updated supplier information`, undefined, undefined, old?.projectId);
  };

  const deleteSupplier = (id: string) => {
    const old = suppliers.find(s => s.id === id);
    setSuppliers(prev => prev.filter(s => s.id !== id));
    logAudit('delete', 'supplier', id, old?.name || 'Supplier', `Deleted supplier`, undefined, undefined, old?.projectId);
  };

  // Labor & Payroll Records
  const addLaborRecord = (record: Omit<LaborRecord, 'id' | 'createdAt'>) => {
    const id = 'lab-' + crypto.randomUUID();
    const newRecord: LaborRecord = {
      approvalStatus: 'draft', approvalHistory: [],
      ...record,
      grossWage: payrollAmounts(record).gross,
      netPayable: payrollAmounts(record).net,
      date: record.date || todayISO(),
      id,
      createdAt: new Date().toISOString()
    };
    setLaborRecords(prev => [newRecord, ...prev]);
    logAudit('create', 'expense', id, record.workerName, `ثبت معاش کارگری برای ${record.workerName}: ${record.netPayable} AFN`, undefined, undefined, record.projectId);
  };

  const updateLaborRecord=(id:string,updates:Partial<LaborRecord>)=>{
    const old=laborRecords.find(w=>w.id===id);if(!old||old.approvalStatus&&old.approvalStatus!=='draft')throw new Error('Payroll is locked.');
    const next={...old,...updates},amounts=payrollAmounts(next);setLaborRecords(prev=>prev.map(w=>w.id===id?{...next,grossWage:amounts.gross,netPayable:amounts.net}:w));
    logAudit('update','expense',id,next.workerName,'Updated payroll draft',undefined,undefined,old.projectId);
  };

  const deleteLaborRecord = (id: string) => {
    const old = laborRecords.find(l => l.id === id);
    setLaborRecords(prev => prev.filter(l => l.id !== id));
    logAudit('delete', 'expense', id, old?.workerName || 'Labor Record', `حذف رکورد معاش کارگری`, undefined, undefined, old?.projectId);
  };

  // Fixed Assets & Heavy Machinery CRUD
  const addAsset = (asset: Omit<FixedAsset, 'id' | 'createdAt'>) => {
    const id = 'ast-' + Date.now();
    const newAsset: FixedAsset = {
      ...asset,
      id,
      createdAt: new Date().toISOString()
    };
    setAssets(prev => [newAsset, ...prev]);
    logAudit('create', 'expense', id, asset.name, `ثبت دارایی ثابت/ماشین‌آلات: ${asset.name} (${asset.assetTag}) - قیمت: $${asset.purchaseCost}`, undefined, undefined, asset.assignedProjectId);
  };

  const updateAsset = (id: string, updates: Partial<FixedAsset>) => {
    const old = assets.find(a => a.id === id);
    setAssets(prev => prev.map(a => (a.id === id ? { ...a, ...updates } : a)));
    logAudit('update', 'expense', id, updates.name || old?.name || 'Asset', `ویرایش مشخصات ماشین‌آلات/دارایی`, undefined, undefined, old?.assignedProjectId);
  };

  const deleteAsset = (id: string) => {
    const old = assets.find(a => a.id === id);
    setAssets(prev => prev.filter(a => a.id !== id));
    logAudit('delete', 'expense', id, old?.name || 'Asset', `حذف دارایی ثابت/ماشین‌آلات`, undefined, undefined, old?.assignedProjectId);
  };

  const previewGeometry=(next:ApartmentUnit[])=>{if(!IS_PREVIEW)return;setProjects(rows=>rows.map(project=>{if(!apartments.some(u=>u.projectId===project.id&&u.status==='sold'&&(!u.approvalStatus||u.approvalStatus==='posted')))return project;const area=(units:ApartmentUnit[])=>computeSellableAreaM2(inventoryUnits(units).filter(u=>u.projectId===project.id),project);const before=area(apartments),after=area(next),history=[...(project.inventoryAreaHistory?.length?project.inventoryAreaHistory:[{date:'0001-01-01',totalAreaM2:before}])];if(before!==after){if(history.at(-1)?.date===todayISO())history[history.length-1]={date:todayISO(),totalAreaM2:after};else history.push({date:todayISO(),totalAreaM2:after});}return {...project,inventoryAreaHistory:history};}));};
  // Apartments & Sales CRUD
  const addApartment = (data: Omit<ApartmentUnit, 'id' | 'salePrice' | 'remainingBalance' | 'createdAt'> & { salePrice?: number }) => {
    if(blankTestMode)throw new Error('Apartments and sales are locked in this test.');
    const pricePerM2 = data.pricePerM2 || (data.salePrice && data.areaM2 ? data.salePrice / data.areaM2 : 0);
    const salePrice = data.salePrice || roundMoney(data.areaM2 * pricePerM2);
    const amountReceived = data.amountReceived || 0;
    const remainingBalance = salePrice - amountReceived;
    const newUnit: ApartmentUnit = {
      approvalStatus: 'draft', approvalHistory: [],
      ...data,
      id: 'unit-' + crypto.randomUUID(),
      pricePerM2,
      salePrice,
      amountReceived,
      remainingBalance,
      createdAt: new Date().toISOString(),
    };
    if((data.downPayment||0)>0&&!canAccess(currentUser,data.projectId,'payments','create',appSettings))throw new Error('Payment permission required.');
    previewGeometry([...apartments,newUnit]);
    setApartments(prev => [...prev, newUnit]);
    if((data.downPayment||0)>0)addPayment({projectId:newUnit.projectId,date:saleDate(newUnit)||todayISO(),amount:data.downPayment!,currency:newUnit.currency||'USD',exchangeRate:newUnit.exchangeRate||70,method:'Cash',relatedType:'apartment_sale',relatedId:newUnit.id,partyName:newUnit.buyerName||newUnit.unitNumber,description:uiText(language,'downPayment')+' · '+newUnit.unitNumber,generatedDescription:'downPayment'});
    logAudit('create', 'apartment', newUnit.id, `Unit #${newUnit.unitNumber} (Floor ${newUnit.floor})`, `Added apartment unit: ${newUnit.areaM2} m², Status: ${newUnit.status}${newUnit.buyerName ? ` - Buyer: ${newUnit.buyerName}` : ''}`, undefined, `$${newUnit.salePrice}`, newUnit.projectId);
  };

  const updateApartment = (id: string, updates: Partial<ApartmentUnit>) => {
    if(blankTestMode)throw new Error('Apartments and sales are locked in this test.');
    const old = apartments.find(a => a.id === id);
    if (!old) return;
    if(old.status==='sold'&&(!old.approvalStatus||old.approvalStatus==='posted')&&['areaM2','areaSqm','unitNumber','floor','projectId','buildingSection','status','contractDate','saleDate','salePrice','totalPrice','currency','exchangeRate'].some(k=>(updates as any)[k]!==undefined&&(updates as any)[k]!== (old as any)[k]))throw new Error('Historical geometry is locked');
    previewGeometry(apartments.map(r=>r.id===id?{...r,...updates}:r));
    const areaM2 = updates.areaM2 !== undefined ? updates.areaM2 : old.areaM2;
    const pricePerM2 = updates.pricePerM2 !== undefined ? updates.pricePerM2 : old.pricePerM2 || 0;
    const salePrice = updates.salePrice !== undefined ? updates.salePrice : (pricePerM2 > 0 ? roundMoney(areaM2 * pricePerM2) : (old.salePrice || 0));
    const amountReceived = updates.amountReceived !== undefined ? updates.amountReceived : (old.amountReceived || 0);
    const remainingBalance = salePrice - amountReceived;
    setApartments(prev => prev.map(a => (a.id === id ? {
      ...a,
      ...updates,
      areaM2,
      pricePerM2,
      salePrice,
      amountReceived,
      remainingBalance
    } : a)));
    logAudit('update', 'apartment', id, `Unit #${updates.unitNumber || old.unitNumber}`, `Updated apartment / buyer details`, `$${old.salePrice}`, `$${salePrice}`, old.projectId);
  };

  const deleteApartment = (id: string) => {
    if(blankTestMode)throw new Error('Apartments and sales are locked in this test.');
    const old = apartments.find(a => a.id === id);
    if(old?.status==='sold'&&(!old.approvalStatus||old.approvalStatus==='posted'))throw new Error('Historical geometry is locked');
    previewGeometry(apartments.filter(r=>r.id!==id));
    setApartments(prev => prev.filter(a => a.id !== id));
    logAudit('delete', 'apartment', id, `Unit #${old?.unitNumber}`, `Deleted apartment unit`, `$${old?.salePrice}`, undefined, old?.projectId);
  };

  // Installments Management

  const addInstallmentToApartment = (apartmentId: string, installmentData: Omit<InstallmentRecord, 'id' | 'remainingAmount' | 'status' | 'paymentHistory'>) => {
    if(blankTestMode)throw new Error('Apartments and sales are locked in this test.');
    setApartments(prev => prev.map(apt => {
      if (apt.id !== apartmentId) return apt;
      const currentInsts = apt.approvalStatus?(apt.installments||[]):normalizeApartmentInstallments(apt);
      const paid = installmentData.paidAmount || 0;
      const remaining = Math.max(0, installmentData.amount - paid);
      const status = calculateInstallmentStatus(installmentData.amount, paid, installmentData.dueDate);
      const newInst: InstallmentRecord = {
        ...installmentData,
        id: `inst-${apt.id}-${Date.now()}`,
        installmentNumber: installmentData.installmentNumber || (currentInsts.length + 1),
        paidAmount: paid,
        remainingAmount: remaining,
        status,
        paymentHistory: paid > 0 ? [{
          id: `payhist-${Date.now()}`,
          paymentNumber: 1,
          amount: paid,
          currency: installmentData.currency || apt.currency || 'USD',
          date: installmentData.dueDate,
          notes: '',
        }] : [],
        received: remaining === 0,
        receivedDate: remaining === 0 ? installmentData.dueDate : undefined,
      };
      const updatedInsts = [...currentInsts, newInst];
      return {
        ...apt,
        installments: updatedInsts,
      };
    }));
  };

  // Payments CRUD
  const addPayment = (paymentData: Omit<Payment, 'id' | 'createdAt' | 'createdBy'>) => {
    if(blankTestMode&&paymentData.relatedType==='apartment_sale')throw new Error('Sales are locked in this test.');
    const time = paymentData.time || new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
    const newPayment: Payment = {
      approvalStatus: 'draft', approvalHistory: [],
      ...paymentData,
      time,
      id: 'pay-' + crypto.randomUUID(),
      createdAt: new Date().toISOString(),
      createdBy: currentUser?.name || 'Admin',
    };
    setPayments(prev => [newPayment, ...prev]);

    if (!newPayment.approvalStatus) {
    if (paymentData.relatedType === 'apartment_sale' && paymentData.relatedId) {
      setApartments(prev => prev.map(u => {
        if (u.id === paymentData.relatedId) {
          const currentReceived = u.amountReceived || 0;
          const newReceived = currentReceived + paymentData.amount;
          const salePrice = u.salePrice || 0;
          return {
            ...u,
            amountReceived: newReceived,
            remainingBalance: Math.max(0, salePrice - newReceived),
            status: newReceived >= salePrice ? 'sold' : u.status,
          };
        }
        return u;
      }));
    } else if ((paymentData.relatedType === 'material_steel' || paymentData.relatedType === 'steel') && paymentData.relatedId) {
      setSteelRecords(prev => prev.map(s => {
        if (s.id === paymentData.relatedId) {
          const paidAmount = (s.paidAmount || 0) + paymentData.amount;
          return {
            ...s,
            paidAmount,
            remainingBalance: Math.max(0, s.totalAmount - paidAmount),
          };
        }
        return s;
      }));
    } else if ((paymentData.relatedType === 'material_concrete' || paymentData.relatedType === 'concrete') && paymentData.relatedId) {
      setConcreteRecords(prev => prev.map(c => {
        if (c.id === paymentData.relatedId) {
          const paidAmount = (c.paidAmount || 0) + paymentData.amount;
          return {
            ...c,
            paidAmount,
            remainingBalance: Math.max(0, c.totalAmount - paidAmount),
          };
        }
        return c;
      }));
    } else if (paymentData.relatedType === 'contractor') {
      setContractors(prev => prev.map(c => {
        if (c.id === paymentData.relatedId || c.name.toLowerCase() === paymentData.partyName.toLowerCase()) {
          const currentPaid = c.totalPaid || 0;
          const newPaid = currentPaid + paymentData.amount;
          return {
            ...c,
            totalPaid: newPaid,
            remainingBalance: Math.max(0, c.contractAmount - newPaid),
          };
        }
        return c;
      }));
    } else if (paymentData.relatedType === 'supplier') {
      setSuppliers(prev => prev.map(s => {
        if (s.id === paymentData.relatedId || s.name.toLowerCase() === paymentData.partyName.toLowerCase()) {
          const currentPaid = s.totalPaid || 0;
          const newPaid = currentPaid + paymentData.amount;
          const purchases = s.totalPurchases || 0;
          return {
            ...s,
            totalPaid: newPaid,
            remainingBalance: Math.max(0, purchases - newPaid),
          };
        }
        return s;
      }));
    } else if (paymentData.relatedType === 'expense' && paymentData.relatedId) {
      const targetExp = expenses.find(e => e.id === paymentData.relatedId);
      setExpenses(prev => prev.map(e => {
        if (e.id === paymentData.relatedId) {
          const paidAmount = (e.paidAmount || 0) + paymentData.amount;
          return {
            ...e,
            paidAmount,
            remainingAmount: Math.max(0, e.amount - paidAmount),
            paymentStatus: paidAmount >= e.amount ? 'paid' : (paidAmount > 0 ? 'partial' : 'unpaid'),
          };
        }
        return e;
      }));
      const supplierName = targetExp?.partyName || paymentData.partyName;
      if (supplierName && supplierName.trim()) {
        setSuppliers(prev => prev.map(s => {
          if (s.projectId === (targetExp?.projectId || currentProjectId) && s.name.trim().toLowerCase() === supplierName.trim().toLowerCase()) {
            const currentPaid = (s.totalPaid || 0) + paymentData.amount;
            const purchases = s.totalPurchases || 0;
            return {
              ...s,
              totalPaid: currentPaid,
              remainingBalance: Math.max(0, purchases - currentPaid),
            };
          }
          return s;
        }));
      }
    }
    }
    logAudit('create', 'payment', newPayment.id, `Payment to/from: ${newPayment.partyName}`, `Amount: $${newPayment.amount.toLocaleString()} (${newPayment.method}) - ${newPayment.description}`, undefined, `$${newPayment.amount}`, newPayment.projectId);
  };

  const deletePayment = (id: string) => {
    const old = payments.find(p => p.id === id);
    if (!old) return;
    setPayments(prev => prev.filter(p => p.id !== id));

    if (!old.approvalStatus) {
    if (old.relatedType === 'apartment_sale' && old.relatedId) {
      setApartments(prev => prev.map(u => {
        if (u.id === old.relatedId) {
          const currentReceived = u.amountReceived || 0;
          const newReceived = Math.max(0, currentReceived - old.amount);
          const salePrice = u.salePrice || 0;
          return {
            ...u,
            amountReceived: newReceived,
            remainingBalance: Math.max(0, salePrice - newReceived),
            status: newReceived < salePrice && u.status === 'sold' ? 'reserved' : u.status,
          };
        }
        return u;
      }));
    } else if ((old.relatedType === 'material_steel' || old.relatedType === 'steel') && old.relatedId) {
      setSteelRecords(prev => prev.map(s => {
        if (s.id === old.relatedId) {
          const paidAmount = Math.max(0, (s.paidAmount || 0) - old.amount);
          return {
            ...s,
            paidAmount,
            remainingBalance: Math.max(0, s.totalAmount - paidAmount),
          };
        }
        return s;
      }));
    } else if ((old.relatedType === 'material_concrete' || old.relatedType === 'concrete') && old.relatedId) {
      setConcreteRecords(prev => prev.map(c => {
        if (c.id === old.relatedId) {
          const paidAmount = Math.max(0, (c.paidAmount || 0) - old.amount);
          return {
            ...c,
            paidAmount,
            remainingBalance: Math.max(0, c.totalAmount - paidAmount),
          };
        }
        return c;
      }));
    } else if (old.relatedType === 'contractor') {
      setContractors(prev => prev.map(c => {
        if (c.id === old.relatedId || c.name.toLowerCase() === old.partyName.toLowerCase()) {
          const currentPaid = c.totalPaid || 0;
          const newPaid = Math.max(0, currentPaid - old.amount);
          return {
            ...c,
            totalPaid: newPaid,
            remainingBalance: Math.max(0, c.contractAmount - newPaid),
          };
        }
        return c;
      }));
    } else if (old.relatedType === 'supplier') {
      setSuppliers(prev => prev.map(s => {
        if (s.id === old.relatedId || s.name.toLowerCase() === old.partyName.toLowerCase()) {
          const currentPaid = s.totalPaid || 0;
          const newPaid = Math.max(0, currentPaid - old.amount);
          const purchases = s.totalPurchases || 0;
          return {
            ...s,
            totalPaid: newPaid,
            remainingBalance: Math.max(0, purchases - newPaid),
          };
        }
        return s;
      }));
    } else if (old.relatedType === 'expense' && old.relatedId) {
      const targetExp = expenses.find(e => e.id === old.relatedId);
      setExpenses(prev => prev.map(e => {
        if (e.id === old.relatedId) {
          const paidAmount = Math.max(0, (e.paidAmount || 0) - old.amount);
          return {
            ...e,
            paidAmount,
            remainingAmount: Math.max(0, e.amount - paidAmount),
            paymentStatus: paidAmount >= e.amount ? 'paid' : (paidAmount > 0 ? 'partial' : 'unpaid'),
          };
        }
        return e;
      }));
      const supplierName = targetExp?.partyName || old.partyName;
      if (supplierName && supplierName.trim()) {
        setSuppliers(prev => prev.map(s => {
          if (s.projectId === (targetExp?.projectId || old.projectId) && s.name.trim().toLowerCase() === supplierName.trim().toLowerCase()) {
            const currentPaid = Math.max(0, (s.totalPaid || 0) - old.amount);
            const purchases = s.totalPurchases || 0;
            return {
              ...s,
              totalPaid: currentPaid,
              remainingBalance: Math.max(0, purchases - currentPaid),
            };
          }
          return s;
        }));
      }
    }
    }
    logAudit('delete', 'payment', id, `Payment: ${old?.partyName}`, `Deleted payment voucher #${old?.receiptNumber || id}`, `$${old?.amount}`, undefined, old?.projectId);
  };

  const updatePayment = (id: string, updates: Partial<Payment>) => {
    setPayments(prev => prev.map(p => (p.id === id ? { ...p, ...updates } : p)));
    logAudit('update', 'payment', id, updates.partyName || 'Payment', 'Updated payment details', undefined, undefined, currentProjectId);
  };

  // Documents CRUD & Camera Capture
  const addDocument = (docData: Omit<DocumentRecord, 'id' | 'createdAt'>) => {
    const newDoc: DocumentRecord = {
      ...docData,
      id: 'doc-' + Date.now(),
      createdAt: new Date().toISOString(),
    };
    setDocuments(prev => [newDoc, ...prev]);
    logAudit('create', 'expense', newDoc.id, `Document: ${newDoc.title}`, `Category: ${newDoc.category} - File: ${newDoc.fileName}`, undefined, undefined, newDoc.projectId);
  };

  const updateDocument = (id:string,updates:Partial<DocumentRecord>) => {
    const old=documents.find(d=>d.id===id);
    setDocuments(prev=>prev.map(d=>d.id===id?{...d,...updates,id:d.id,projectId:d.projectId,createdAt:d.createdAt}:d));
    logAudit('update','document',id,updates.title||old?.title||id,'Updated document',undefined,undefined,old?.projectId);
  };

  const deleteDocument = (id: string) => {
    const old = documents.find(d => d.id === id);
    setDocuments(prev => prev.filter(d => d.id !== id));
    logAudit('delete', 'expense', id, `Document: ${old?.title}`, `Removed attached document`, undefined, undefined, old?.projectId);
  };

  // Google Drive: Backup database to Google Drive
  const backupToGoogleDrive = async (): Promise<{ success: boolean; fileLink?: string; error?: string }> => {
    try {
      const token = await getAccessToken();
      if (!token) {
        const connected = await connectGoogleDrive();
        if (!connected) {
          return { success: false, error: 'لطفاً ابتدا با حساب گوگل خود وارد شوید.' };
        }
      }

      // 1. Create/Find parent folder "NIK SMART COUNT"
      const parentFolderId = await getOrCreateFolder('NSC Company '+(workspaceCompany||currentUser?.ownerAdminId||currentUser?.id||''));
      // 2. Create/Find project subfolder
      const projFolderId = await getOrCreateFolder('Project '+(currentProject?.id||'main'), parentFolderId);

      // 3. Prepare JSON backup
      const backupData = {
      ...(IS_PREVIEW?{localAttachments:await exportLocalFiles()}:{}),
        appName: 'NIK SMART COUNT Database',
        exportedAt: new Date().toISOString(),
        project: currentProject,
        projects,
        expenses,
        steelRecords,
        concreteRecords,
        contractors,
        suppliers,
        apartments,
        payments,
        documents,
        projectPartners,
        projectInvestments,projectTransfers,creditNotes,
        laborRecords,assets,projectBudgets,partnerEquityTransfers,auditLogs,notifications,
        appSettings,
      };

      const dateStr = new Date().toISOString().split('T')[0];
      const fileName = `Backup_${(currentProject?.name || 'Project').replace(/\s+/g, '_')}_${dateStr}.json`;

      const uploaded = await uploadFileToGoogleDrive({
        fileName,
        mimeType: 'application/json',
        content: JSON.stringify(backupData, null, 2),
        folderId: projFolderId,
      });

      // Also record as a document in the app
      addDocument({
        projectId: currentProjectId,
        title: `Google Drive Backup - ${dateStr}`,
        category: 'backup',
        fileUrl: uploaded.webViewLink || '#',
        fileName,
        fileType: 'application/json',
        date: dateStr,
        googleDriveFileId: uploaded.id,
        googleDriveLink: uploaded.webViewLink,
        notes: '',
      });

      return { success: true, fileLink: uploaded.webViewLink };
    } catch (err: any) {
      console.error('Backup to Google Drive error:', err);
      return { success: false, error: err.message || 'خطا در پشتیبان‌گیری در گوگل درایو' };
    }
  };

  // Google Drive: Upload specific document to Google Drive

  /* ------------------------------------------------------------------ */
  /* Accounting layer: auto-derived double-entry journal                  */
  /* ------------------------------------------------------------------ */
  const journalEntries = useMemo<JournalEntry[]>(() => {
    return buildJournal({
      laborRecords,
      projects,
      expenses,
      steelRecords,
      concreteRecords,
      payments,
      apartments,
      projectInvestments,projectTransfers,
    });
  }, [projects, expenses, steelRecords, concreteRecords, payments, apartments, projectInvestments,projectTransfers,creditNotes, laborRecords]);

  const accountBalances = useMemo<AccountBalance[]>(() => {
    return computeAccountBalances(journalEntries);
  }, [journalEntries]);

  const getProjectJournal = (projectId: string): JournalEntry[] =>
    journalEntries.filter(e => e.projectId === projectId);

  const getAccountDisplayName = (code: string, lang?: Language): string => {const custom=(appSettings as any).dashboardDesign?.accountTexts?.[code]?.[lang||language];return typeof custom==='string'&&custom.trim()?custom:getAccountName(code,lang||language);};

  /* ------------------------------------------------------------------ */
  /* Project Budget (per project, per COA account)                      */
  /* ------------------------------------------------------------------ */


  const getBudgetReport = (projectId: string): BudgetLineReport[] =>
    computeBudgetReport({
      payments,projectTransfers,
      laborRecords,
      projectId,
      project: projects.find(p => p.id === projectId),
      budgets: projectBudgets,
      expenses,
      steelRecords,
      concreteRecords,
    });

  const budgetWithNorm = (
    data: { amount: number; currency: string; exchangeRate?: number; projectId: string }
  ) => {
    const proj = projects.find(p => p.id === data.projectId);
    const rate = data.exchangeRate || proj?.defaultExchangeRate || 70;
    const amountUSD = data.currency === 'USD' ? data.amount : (rate > 0 ? roundMoney((data.amount / rate)) : data.amount);
    const amountAFN = data.currency === 'AFN' ? roundMoney(data.amount) : roundMoney(data.amount * rate);
    return { exchangeRate: rate, amountUSD, amountAFN };
  };

  const addProjectBudget = (data: Omit<ProjectBudget, 'id' | 'createdAt' | 'createdBy'>) => {
    // Standard: single budget line per (project, account). If one exists,
    // update it instead of creating a duplicate row.
    const existing = projectBudgets.find(b => b.projectId === data.projectId && b.accountCode === data.accountCode);
    if (existing) {
      updateProjectBudget(existing.id, data);
      return {...existing,...data,...budgetWithNorm(data)};
    }
    const norm = budgetWithNorm(data);
    const newBudget: ProjectBudget = {
      ...data,
      ...norm,
      id: crypto.randomUUID(),
      createdAt: new Date().toISOString(),
      createdBy: currentUser?.name || 'Admin',
    };
    setProjectBudgets(prev => [...prev, newBudget]);
    logAudit('create', 'expense', newBudget.id, `Budget: ${getAccountName(newBudget.accountCode, 'fa')}`, `Set budget ${newBudget.amount.toLocaleString()} ${newBudget.currency} for account ${newBudget.accountCode}`, undefined, `${newBudget.amount} ${newBudget.currency}`, newBudget.projectId);
    return newBudget;
  };

  const updateProjectBudget = (id: string, updates: Partial<ProjectBudget>) => {
    const old = projectBudgets.find(b => b.id === id);
    if (!old) return;
    const merged = { ...old, ...updates };
    const norm = budgetWithNorm({ amount: merged.amount, currency: merged.currency, exchangeRate: updates.exchangeRate, projectId: merged.projectId });
    setProjectBudgets(prev => prev.map(b => (b.id === id ? { ...merged, ...norm, updatedAt: new Date().toISOString() } : b)));
    logAudit('update', 'expense', id, `Budget: ${getAccountName(merged.accountCode, 'fa')}`, `Updated budget for account ${merged.accountCode}`, `${old.amount} ${old.currency}`, `${merged.amount} ${merged.currency}`, merged.projectId);
  };

  const deleteProjectBudget = (id: string) => {
    const old = projectBudgets.find(b => b.id === id);
    if (!old) return;
    setProjectBudgets(prev => prev.filter(b => b.id !== id));
    logAudit('delete', 'expense', id, `Budget: ${getAccountName(old.accountCode, 'fa')}`, `Removed budget of ${old.amount.toLocaleString()} ${old.currency} (account ${old.accountCode})`, `${old.amount} ${old.currency}`, undefined, old.projectId);
  };

  const exportJsonBackup = async () => {
    const backupData = {
      ...(IS_PREVIEW?{localAttachments:await exportLocalFiles()}:{}),
      version: '2.0.0',
      exportedAt: new Date().toISOString(),
      appName: 'Nik Smart Count Database',
      projects,
      projectPartners,
      projectInvestments,projectTransfers,creditNotes,
      expenses,
      steelRecords,
      concreteRecords,
      contractors,
      suppliers,
      apartments,
      payments,
      documents,
      appSettings,
      laborRecords, assets, projectBudgets, partnerEquityTransfers, auditLogs, notifications,
    };
    const jsonStr = JSON.stringify(backupData, null, 2);
    if(IS_PREVIEW&&new Blob([jsonStr]).size>26214400)throw new Error('Backup exceeds 25 MB.');
    const blob = new Blob([jsonStr], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `nik_smart_count_backup_${new Date().toISOString().split('T')[0]}.json`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  };

  const restoreJsonBackup = async (jsonContent: string) => {
    try {
      const data = JSON.parse(jsonContent);
      if (!data || typeof data !== 'object' || !Array.isArray(data.projects)||data.appSettings!==undefined&&(!data.appSettings||typeof data.appSettings!=='object'||Array.isArray(data.appSettings))) throw new Error('Invalid backup');
      for (const key of ['projects','expenses','steelRecords','concreteRecords','apartments','payments','contractors','suppliers','documents','laborRecords','assets','projectBudgets','projectPartners','projectInvestments','projectTransfers','creditNotes','partnerEquityTransfers']) if (data[key] !== undefined && (!Array.isArray(data[key]) || data[key].some((r:any)=>!r || typeof r.id !== 'string'))) throw new Error('Invalid backup collection');
      if(blankTestMode&&((data.apartments||[]).length||(data.payments||[]).some((p:any)=>p.relatedType==='apartment_sale')))throw new Error('Sales are locked in this test.');
      if(IS_PREVIEW)await restoreLocalFiles(data.localAttachments);
      if (Array.isArray(data.projects)) setProjects(data.projects);
      if (Array.isArray(data.projectPartners)) setProjectPartners(data.projectPartners);
      if (Array.isArray(data.creditNotes)) setCreditNotes(data.creditNotes);
      if (Array.isArray(data.projectTransfers)) setProjectTransfers(data.projectTransfers);
      if (Array.isArray(data.projectInvestments)) setProjectInvestments(data.projectInvestments);
      if (Array.isArray(data.expenses)) setExpenses(data.expenses);
      if (Array.isArray(data.laborRecords)) setLaborRecords(data.laborRecords);
      if (Array.isArray(data.assets)) setAssets(data.assets);
      if (Array.isArray(data.projectBudgets)) setProjectBudgets(data.projectBudgets);
      if (Array.isArray(data.partnerEquityTransfers)) setPartnerEquityTransfers(data.partnerEquityTransfers);
      if (Array.isArray(data.auditLogs)) setAuditLogs(data.auditLogs);
      if (Array.isArray(data.notifications)) setNotifications(data.notifications);
      if (Array.isArray(data.steelRecords)) setSteelRecords(data.steelRecords);
      if (Array.isArray(data.concreteRecords)) setConcreteRecords(data.concreteRecords);
      if (Array.isArray(data.contractors)) setContractors(data.contractors);
      if (Array.isArray(data.suppliers)) setSuppliers(data.suppliers);
      if (Array.isArray(data.apartments)) setApartments(data.apartments);
      if (Array.isArray(data.payments)) setPayments(data.payments);
      if (Array.isArray(data.documents)) setDocuments(data.documents);
      if(data.appSettings){const incoming={...data.appSettings};if(!currentUser?.isOffice7Owner&&!currentUser?.isMasterSuperAdmin)for(const key of ['dashboardDesign','designHistory','companyProfile','documentTemplates'])incoming[key]=(appSettings as any)[key];setAppSettings(incoming);}
      return { success: true };
    } catch (err: any) {
      return { success: false, error: err.message || 'فایل نامعتبر است' };
    }
  };

  const [isAiAssistantOpen, setIsAiAssistantOpen] = useState(false);
  const [isMasterAdminOpen, setIsMasterAdminOpen] = useState(false);

  const formatCurrency = (amount: number | undefined, currency: string = 'USD'): string => {
    if (amount === undefined || isNaN(amount)) return new Intl.NumberFormat(uiLocales[language],{numberingSystem:numberingSystems[language]}).format(0)+' '+currency;
    const formatted = new Intl.NumberFormat(uiLocales[language],{maximumFractionDigits:2,numberingSystem:numberingSystems[language]}).format(amount);
    if (currency === 'USD') return `$${formatted}`;
    if (currency === 'AFN') return `${formatted} ؋`;
    return `${formatted} ${currency}`;
  };

  const formatNumber = (value: number | undefined, decimals: number = 0): string => {
    if (value === undefined || isNaN(value)) return new Intl.NumberFormat(uiLocales[language],{numberingSystem:numberingSystems[language]}).format(0);
    return Number(value).toLocaleString(uiLocales[language], {
      numberingSystem:numberingSystems[language],
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    });
  };

  const exportBackupJSON = exportJsonBackup;

  const workspaceDocument = useMemo(() => ({projects:projects,expenses:expenses,steel:steelRecords,concrete:concreteRecords,contractors:contractors,suppliers:suppliers,labor:laborRecords,assets:assets,apartments:apartments,payments:payments,documents:documents,auditLogs:auditLogs,activityEvents:activityEvents,projectPartners:projectPartners,projectInvestments:projectInvestments,projectTransfers,creditNotes,partnerEquityTransfers:partnerEquityTransfers,projectBudgets:projectBudgets,notifications:notifications, appSettings, currentProjId: currentProjectId }), [projects,expenses,steelRecords,concreteRecords,contractors,suppliers,laborRecords,assets,apartments,payments,documents,auditLogs,activityEvents,projectPartners,projectInvestments,projectTransfers,creditNotes,partnerEquityTransfers,projectBudgets,notifications,appSettings,currentProjectId]);
  const sync = useWorkspaceSync(currentUser ? currentUser.id+':'+(workspaceCompany||'') : undefined,workspaceDocument,doc=> {
    setProjects(Array.isArray(doc?.projects) ? doc!.projects : []);
    setExpenses(Array.isArray(doc?.expenses) ? doc!.expenses : []);
    setSteelRecords(Array.isArray(doc?.steel) ? doc!.steel : []);
    setConcreteRecords(Array.isArray(doc?.concrete) ? doc!.concrete : []);
    setContractors(Array.isArray(doc?.contractors) ? doc!.contractors : []);
    setSuppliers(Array.isArray(doc?.suppliers) ? doc!.suppliers : []);
    setLaborRecords(Array.isArray(doc?.labor) ? doc!.labor : []);
    setAssets(Array.isArray(doc?.assets) ? doc!.assets : []);
    setApartments(Array.isArray(doc?.apartments) ? doc!.apartments : []);
    setPayments(Array.isArray(doc?.payments) ? doc!.payments : []);
    setDocuments(Array.isArray(doc?.documents) ? doc!.documents : []);
    setAuditLogs(Array.isArray(doc?.auditLogs) ? doc!.auditLogs : []);
    setActivityEvents(Array.isArray(doc?.activityEvents) ? doc!.activityEvents : []);
    setProjectPartners(Array.isArray(doc?.projectPartners) ? doc!.projectPartners : []);
    setCreditNotes(Array.isArray(doc?.creditNotes)?doc!.creditNotes:[]);
    setProjectTransfers(Array.isArray(doc?.projectTransfers)?doc!.projectTransfers:[]);
    setProjectInvestments(Array.isArray(doc?.projectInvestments) ? doc!.projectInvestments : []);
    setPartnerEquityTransfers(Array.isArray(doc?.partnerEquityTransfers) ? doc!.partnerEquityTransfers : []);
    setProjectBudgets(Array.isArray(doc?.projectBudgets) ? doc!.projectBudgets : []);
    setNotifications(Array.isArray(doc?.notifications) ? doc!.notifications : []);
    setCurrentProjectIdState(typeof doc?.currentProjId === 'string' ? doc.currentProjId : '');
    let localOverride:unknown;
    try{localOverride=IS_PREVIEW?loadFromStorage<Language>('lang','en'):currentUser?localStorage.getItem('nsc_ui_language:'+currentUser.id):undefined;}catch{}
    const preferred=resolvePreferredLanguage(doc?.appSettings?.language,localOverride);
    setLanguageState(preferred);saveToStorage('lang',preferred);
    const settings={...defaultAppSettings,...(doc?.appSettings||{}),companyName:doc?.appSettings?.companyName||currentUser?.companyName||defaultAppSettings.companyName};
    setAppSettings(settings);
    const rows=(key:string)=>Array.isArray(doc?.[key])?doc![key]:[];
    // Return exactly the hydrated client shape so defaults/redacted staff views do not trigger save loops.
    return {projects:rows('projects'),expenses:rows('expenses'),steel:rows('steel'),concrete:rows('concrete'),contractors:rows('contractors'),suppliers:rows('suppliers'),labor:rows('labor'),assets:rows('assets'),apartments:rows('apartments'),payments:rows('payments'),documents:rows('documents'),auditLogs:rows('auditLogs'),activityEvents:rows('activityEvents'),projectPartners:rows('projectPartners'),projectInvestments:rows('projectInvestments'),projectTransfers:rows('projectTransfers'),creditNotes:rows('creditNotes'),partnerEquityTransfers:rows('partnerEquityTransfers'),projectBudgets:rows('projectBudgets'),notifications:rows('notifications'),appSettings:settings,currentProjId:typeof doc?.currentProjId==='string'?doc.currentProjId:''};
  }, currentUser || undefined);
  return (
    <AppContext.Provider value={{
      workspaceCompany,switchCompanyWorkspace,workspaceStatus:sync.status,workspaceError:sync.error,publishWebsiteStudio:sync.publishStudio,retryWorkspaceSave:sync.retry,transitionFinancial:sync.transition,
      language,
      setLanguage,
      currentUser,
      login,
      logout,
      users,
      addUser,
      updateUser,
      toggleUserActive,
      requestPasswordReset,
      completePasswordReset,
      toggleDarkMode,
      setTheme,
      activeTheme:personalAppearance.theme,
      projects,
      currentProject,
      setCurrentProjectId,
      addProject,
      updateProject,
      deleteProject,
      projectPartners,
      projectInvestments,projectTransfers,creditNotes,
      addProjectPartner,
      updateProjectPartner,
      deleteProjectPartner,
      addCreditNote,updateCreditNote,deleteCreditNote,
      addProjectTransfer,updateProjectTransfer,deleteProjectTransfer,
      addProjectInvestment,
      updateProjectInvestment,
      deleteProjectInvestment,
      partnerEquityTransfers,
      transferPartnerEquity,
      expenses,
      addExpense,
      updateExpense,
      deleteExpense,
      steelRecords,
      addSteelRecord,
      updateSteelRecord,
      deleteSteelRecord,
      concreteRecords,
      addConcreteRecord,
      updateConcreteRecord,
      deleteConcreteRecord,
      contractors,
      addContractor,
      updateContractor,
      deleteContractor,
      suppliers,
      addSupplier,
      updateSupplier,
      deleteSupplier,
      laborRecords,
      addLaborRecord,updateLaborRecord,
      deleteLaborRecord,
      assets,
      addAsset,
      updateAsset,
      deleteAsset,
      apartments,
      addApartment,
      updateApartment,
      deleteApartment,
      addInstallmentToApartment,
      notifications,
      payments,
      addPayment,
      updatePayment,
      deletePayment,
      appSettings,
      updateAppSettings,
      documents,
      addDocument,
      updateDocument,
      deleteDocument,
      auditLogs,
      chartOfAccounts: CHART_OF_ACCOUNTS,
      getAccountDisplayName,
      getProjectJournal,
      projectBudgets,
      getBudgetReport,
      addProjectBudget,
      updateProjectBudget,
      deleteProjectBudget,
      searchQuery,
      setSearchQuery,
      themeSchedule: personalAppearance.mode,
      setThemeSchedule,
      exportJsonBackup,
      restoreJsonBackup,
      formatCurrency,
      formatNumber,
      isDarkMode: personalAppearance.dark,
      isGoogleDriveConnected,
      connectGoogleDrive,
      disconnectGoogleDrive,
      backupToGoogleDrive,
    }}>
      {children}
    </AppContext.Provider>
  );
};

export const useApp = () => {
  const context = useContext(AppContext);
  if (!context) throw new Error('useApp must be used within an AppProvider');
  return context;
};

/** A design preview changes presentation only; the real workspace remains untouched. */
export function AppDesignPreview({design,language,children}:{design?:any;language?:any;children:React.ReactNode}){const a=useApp();return <AppContext.Provider value={{...a,language:language||a.language,appSettings:{...a.appSettings,...(design?{dashboardDesign:design}: {})} as any,updateAppSettings:()=>{}}}>{children}</AppContext.Provider>;}
