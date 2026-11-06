export interface FinancialReversal {
 amount?: number;id:string;date:string;reason?:string;actorId?:string;actorName?:string;at?:string;}
export interface FinancialApproval {
 reversal?: FinancialReversal;
 creditAdjustments?: Array<FinancialReversal&{amount:number;creditNoteId:string;daysReduction?:number;restoresCredit?:boolean}>;
 partialReversals?: Array<FinancialReversal&{amount:number}>;
 correctionOf?: string;
 approvalStatus?: 'draft'|'submitted'|'approved'|'posted';
 approvalHistory?: {from:string;to:string;actorId:string;actorName:string;at:string}[];
}
export type Language = 'en' | 'fa' | 'ps';
export type UserRole = 'admin' | 'accountant' | 'viewer';

export type MorphismTheme = 
  | 'glassmorphism' 
  | 'neumorphism' 
  | 'skeuomorphism' 
  | 'squirclemorphism' 
  | 'metalmorphism' 
  | 'ar_morphism'
  | 'cosmic_orange'
  | 'blue_titanium'
  | 'desert_titanium';

export type AppTheme = MorphismTheme | 'bento' | 'aurora' | 'handdrawn' | 'figma' | 'claymorphism' | 'slate' | 'navy' | 'emerald' | 'dark_gold';

export interface ReportCustomization {
  style: 'classic' | 'modern' | 'compact' | 'executive';
  showLogo: boolean;
  showSignatures: boolean;
  signature1Title: string;
  signature2Title: string;
  signature3Title: string;
  headerNote?: string;
  footerNote?: string;
  watermarkText?: string;
  uploadedWordTemplateName?: string;
  uploadedTemplateContent?: string;
}

export interface SectionProtectionSettings {
  passwords: Record<string, string>;
  enabled: Record<string, boolean>;
}

export interface GoogleDriveConfig {
  connected: boolean;
  userEmail?: string;
  folderId?: string;
  folderName?: string;
  lastBackupDate?: string;
}

export interface AppSettings {
  language?: Language;
  companyName: string;
  companySubtitle: string;
  logoUrl?: string;
  receiptHeader?: string;
  receiptFooter?: string;
  receiptContact?: string;
  theme: AppTheme;
  darkMode?: boolean;
  themeSchedule?: 'auto' | 'light' | 'dark';
  reduceMotion?: boolean;
  backgroundImageUrl?: string;
  backgroundOpacity?: number;
  customCategories?: string[];
  geminiApiKey?: string;
  aiEnabled?: boolean;
  billDesign?: CustomBillDesign;
  actionButtons?: CustomActionButton[];
  reportCustomization?: ReportCustomization;
  sectionProtection?: SectionProtectionSettings;
  googleDrive?: GoogleDriveConfig;
  exchangeRateUSDToAFN?: number;
  defaultCurrency?: string;
  enabledModules?: {
    steel?: boolean;
    concrete?: boolean;
    expenses?: boolean;
    contractors?: boolean;
    suppliers?: boolean;
    apartments?: boolean;
    payments?: boolean;
    budget?: boolean;
    accounting?: boolean;
    documents?: boolean;
    reports?: boolean;
    auditLogs?: boolean;
    users?: boolean;
    aiAssistant?: boolean;
    googleDrive?: boolean;
    income?: boolean;
    materials?: boolean;
    labor?: boolean;
    treasury?: boolean;
    pettyCash?: boolean;
    assets?: boolean;
    transfers?: boolean;
    journal?: boolean;
  };
}

export type SettlementMode = 'cash' | 'credit' | 'partial';

export interface UserPermissions {
  allowedTabs?: string[];
  canManageSales?: boolean;
  canManageMaterials?: boolean;
  canManageExpenses?: boolean;
  canManageReports?: boolean;
  canManageProjects?: boolean;
  canManagePayments?: boolean;
  canManageSettings?: boolean;
  canManageTreasury?: boolean;
  canManagePayroll?: boolean;
  canManageAssets?: boolean;
  canManageTransfers?: boolean;
  canManageJournal?: boolean;
  aiEnabled?: boolean;
  allowedModules?: string[];
}

export interface CustomActionButton {
  id: string;
  label: string;
  labelFa?: string;
  labelEn?: string;
  actionKey?: string;
  enabled: boolean;
  isEnabled?: boolean;
  color: 'blue' | 'emerald' | 'amber' | 'rose' | 'purple' | 'slate' | 'indigo' | 'orange' | 'primary' | 'success' | 'danger' | 'warning' | 'info';
  shape: 'pill' | 'rounded-xl' | 'rounded-md' | 'square' | 'rounded';
  order: number;
}

export const DEFAULT_ACTION_BUTTONS: CustomActionButton[] = [
  { id: 'addExpense', label: 'ثبت مصارف', enabled: true, color: 'blue', shape: 'rounded-xl', order: 1 },
  { id: 'addPayment', label: 'پرداخت وجه', enabled: true, color: 'emerald', shape: 'rounded-xl', order: 2 },
  { id: 'addIncome', label: 'دریافت عواید', enabled: true, color: 'emerald', shape: 'rounded-xl', order: 3 },
  { id: 'addMaterial', label: 'خرید مصالح', enabled: true, color: 'amber', shape: 'rounded-xl', order: 4 },
  { id: 'addLabor', label: 'معاشات کارگران', enabled: true, color: 'purple', shape: 'rounded-xl', order: 5 },
  { id: 'addSteel', label: 'سیخ‌گول', enabled: true, color: 'amber', shape: 'rounded-xl', order: 6 },
  { id: 'addConcrete', label: 'کانکریت', enabled: true, color: 'blue', shape: 'rounded-xl', order: 7 },
  { id: 'addApartment', label: 'فروش پلاک', enabled: true, color: 'indigo', shape: 'rounded-xl', order: 8 },
  { id: 'addContractor', label: 'قراردادی جدید', enabled: true, color: 'orange', shape: 'rounded-xl', order: 9 },
  { id: 'addTransfer', label: 'حواله پروژه‌ای', enabled: true, color: 'purple', shape: 'rounded-xl', order: 10 },
  { id: 'addPettyCash', label: 'تنخواه کارگاه', enabled: true, color: 'slate', shape: 'rounded-xl', order: 11 },
  { id: 'addAsset', label: 'ثبت ماشین‌آلات', enabled: true, color: 'amber', shape: 'rounded-xl', order: 12 },
];

export interface CustomBillDesign {
  receiptTitle?: string;
  receiptHeader?: string;
  receiptSubheader?: string;
  receiptSubtitle?: string;
  receiptFooter?: string;
  receiptContact?: string;
  companyAddress?: string;
  companyPhone?: string;
  companyEmail?: string;
  logoPosition?: 'left' | 'center' | 'right' | string;
  taxNumber?: string;
  watermarkText?: string;
  themeColor?: string;
  primaryColor?: string;
  accentColor?: string;
  borderStyle?: string;
  showStampArea?: boolean;
  showStampSignature?: boolean;
  showQrCode?: boolean;
  showWithholdingTax?: boolean;
  showRetention?: boolean;
  showRetentionBox?: boolean;
  showWatermark?: boolean;
  termsAndConditions?: string;
  customNote?: string;
  paperSize?: 'A4' | 'A5' | 'thermal';
  customNotesHtml?: string;
}

export interface User {
  companyProfile?:Record<string,string>;
  id: string;
  username: string;
  email: string;
  password?: string;
  name: string;
  phone?: string;
  companyName?: string;
  companyAddress?: string;
  role: UserRole;
  isMasterSuperAdmin?: boolean;
  isOffice7Owner?:boolean;office7Link?:string|null;sessionVersion?:number;licenseStatus?:'active'|'locked'|'companyLocked'|'expired';daysRemaining?:number|null;ownDaysRemaining?:number|null;effectiveExpiresAt?:string|null;subscriptionExpiryDate?:string|null;companyExpiresAt?:string|null;licenseUnlimited?:boolean;locallyDisabled?:boolean;companyLocked?:boolean;
  ownerAdminId?: string;
  active: boolean;
  verified?: boolean;
  otpCode?: string;
  subscriptionPlan?: 'trial' | '6_months' | '1_year' | 'pro' | 'enterprise' | 'lifetime';
  subscriptionExpiresAt?: string;
  isLockedBySuperAdmin?: boolean;
  permissions?: UserPermissions;
  customTheme?: AppTheme;
  customBackgroundUrl?: string;
  customLogoUrl?: string;
  customBillDesign?: CustomBillDesign;
  customButtonConfig?: CustomActionButton[];
  customEnabledModules?: {
    [key:string]:boolean|undefined;
    steel?: boolean;
    concrete?: boolean;
    expenses?: boolean;
    contractors?: boolean;
    suppliers?: boolean;
    apartments?: boolean;
    payments?: boolean;
    budget?: boolean;
    accounting?: boolean;
    documents?: boolean;
    reports?: boolean;
    auditLogs?: boolean;
    users?: boolean;
    income?: boolean;
    materials?: boolean;
    labor?: boolean;
    treasury?: boolean;
    pettyCash?: boolean;
    assets?: boolean;
    transfers?: boolean;
    journal?: boolean;
  };
  createdAt: string;
  lastLoginAt?: string;
  isActive?: boolean;
  allowedTabs?: string[];
}

export type ProjectStatus = 'planning' | 'in_construction' | 'finishing' | 'completed';

export interface GeneralExpenseItem {
  id: string;
  title: string;
  amount: number;
  currency: 'USD' | 'AFN';
  exchangeRate?: number;
  equivalentAmount?: number;
  notes?: string;
}

export interface ProjectPartner {
  id: string;
  projectId: string;
  name: string;
  phone?: string;
  nationalId?: string;
  sharePercentage?: number;
  initialInvestment?: number;
  currency: 'USD' | 'AFN' | string;
  investmentDate?: string;
  notes?: string;
  status?: 'active' | 'exited' | 'transferred';
  exitDate?: string;
  exitReason?: string;
  transferredToPartnerId?: string;
  transferredToPartnerName?: string;
  transferPrice?: number;
  transferCurrency?: 'USD' | 'AFN' | string;
  createdAt: string;
}

export interface PartnerEquityTransfer {
  id: string;
  projectId: string;
  fromPartnerId: string;
  fromPartnerName: string;
  toPartnerId?: string;
  toPartnerName: string;
  transferredPercentage: number;
  transferPrice: number;
  currency: 'USD' | 'AFN' | string;
  transferDate: string;
  transferType: 'partner_to_partner' | 'new_partner' | 'company_buyout';
  deedNumber?: string;
  notes?: string;
  createdAt: string;
}

export interface ProjectInvestment extends FinancialApproval {
  id: string;
  projectId: string;
  partnerId: string;
  partnerName: string;
  amount: number;
  currency: 'USD' | 'AFN' | string;
  exchangeRate?: number;
  date: string;
  type: 'initial' | 'additional';
  paymentMethod?: string;
  receiptNumber?: string;
  notes?: string;
  createdAt: string;
}

export interface ProjectTransferEvent {
  id: string;
  projectId: string;
  previousOwner: string;
  newOwner: string;
  transferDate: string;
  projectStage: string;
  transferValue: number;
  amountPaid: number;
  remainingAmount: number;
  currency: 'USD' | 'AFN' | string;
  contractDocumentUrl?: string;
  contractDocumentName?: string;
  receiptDocumentUrl?: string;
  receiptDocumentName?: string;
  notes?: string;
  createdAt: string;
  createdBy?: string;
}

export interface Project {
  progressPercentage?:number;appearance?:any;
  inventoryAreaHistory?: Array<{date:string;totalAreaM2:number}>;
  id: string;
  name: string;
  code: string;
  address: string;
  city: string;
  clientOwner?: string;
  initialOwner?: string;
  currentOwner?: string;
  landInfo: string;
  floors: number;
  units: number;
  buildingArea: number;
  totalArea?: number;
  startDate?: string;
  expectedCompletionDate?: string;
  status: ProjectStatus;
  description: string;
  projectImage?: string;
  currency: string;
  defaultExchangeRate?: number;
  initialGeneralExpenses?: GeneralExpenseItem[];
  companyName?: string;
  userId?: string;
  budget?: number;
  partners?: ProjectPartner[];
  investments?: ProjectInvestment[];
  transferHistory?: ProjectTransferEvent[];
  floorsCount?: number;
  unitsCount?: number;
  createdAt: string;
}

export type PaymentStatus = 'paid' | 'partial' | 'unpaid';

export interface Expense extends FinancialApproval {
  retentionPercentage?: number;
  taxPercentage?: number;
  id: string;
  projectId: string;
  category: string;
  description: string;
  item?: string;
  quantity?: number;
  unit?: string;
  unitPrice?: number;
  convertedQuantity?: number;
  convertedUnit?: string;
  date: string;
  time?: string;
  amount: number;
  currency: string;
  exchangeRate?: number;
  amountInUSD?: number;
  amountInAFN?: number;
  partyType?: 'supplier' | 'contractor' | 'labour' | 'other';
  partyId?: string;
  partyName?: string;
  paymentStatus?: PaymentStatus;
  paymentMethod?: 'Cash' | 'Bank' | 'Hawala' | 'Personal' | string;
  billNumber?: string;
  paidAmount?: number;
  remainingAmount?: number;
  documentUrl?: string;
  googleDriveFileId?: string;
  googleDriveLink?: string;
  notes?: string;
  title?: string;
  totalAmountUSD?: number;
  totalAmountAFN?: number;
  recipientName?: string;
  recipientId?: string;
  receiptUrl?: string;
  attachments?: string[];
  invoiceNumber?: string;
  createdAt: string;
  createdBy?: string;
}

export interface SteelRecord extends FinancialApproval {
  id: string;
  projectId: string;
  billNumber: string;
  date: string;
  time?: string;
  supplierId?: string;
  supplierName: string;
  kg: number;
  tons: number;
  sizeMm?: string;
  originCountry?: string;
  pricePerTon: number;
  totalAmount: number;
  currency?: string;
  exchangeRate?: number;
  amountInUSD?: number;
  amountInAFN?: number;
  paidAmount: number;
  remainingBalance: number;
  vehicleNumber?: string;
  scaleWeightKg?: number;
  unloadingLocation?: string;
  notes?: string;
  documentUrl?: string;
  googleDriveFileId?: string;
  googleDriveLink?: string;
  size?: string;
  brand?: string;
  invoiceNumber?: string;
  totalKg?: number;
  totalCostUSD?: number;
  bundles?: number | string;
  branches?: number | string;
  createdAt: string;
}

export interface ConcreteRecord extends FinancialApproval {
  id: string;
  projectId: string;
  billNumber: string;
  date: string;
  time?: string;
  supplierId?: string;
  supplierName: string;
  concreteGrade: string;
  elementPoured?: string;
  quantityM3: number;
  pricePerM3: number;
  pumpCharge?: number;
  totalAmount: number;
  currency?: string;
  exchangeRate?: number;
  amountInUSD?: number;
  amountInAFN?: number;
  paidAmount: number;
  remainingBalance: number;
  slumpTestCm?: number;
  labReportNumber?: string;
  mixerCount?: number;
  notes?: string;
  documentUrl?: string;
  googleDriveFileId?: string;
  googleDriveLink?: string;
  volumeM3?: number;
  totalCostUSD?: number;
  structurePart?: string;
  invoiceNumber?: string;
  pumpCost?: number;
  createdAt: string;
}

export interface Contractor {
  id: string;
  projectId: string;
  name: string;
  phone: string;
  contractType: string;
  currency?: 'USD' | 'AFN' | string;
  contractAmount: number;
  workDescription?: string;
  totalPaid?: number;
  remainingBalance?: number;
  startDate?: string;
  endDate?: string;
  paymentTerms?: string;
  retentionPercentage?: number;
  taxPercentage?: number;
  status?: 'active' | 'completed' | 'suspended';
  trade?: string;
  notes?: string;
  createdAt: string;
  documentUrl?: string;
}

export interface Supplier {
  id: string;
  projectId: string;
  name: string;
  contact?: string;
  phone: string;
  currency?: 'USD' | 'AFN' | string;
  materialsSupplied: string;
  category?: string;
  address?: string;
  bankOrSarafiDetails?: string;
  totalPurchases?: number;
  totalPaid?: number;
  remainingBalance?: number;
  notes?: string;
  createdAt: string;
  documentUrl?: string;
}

export type UnitStatus = 'available' | 'reserved' | 'sold';
export type ApartmentStatus = UnitStatus;
export type UnitType = 'apartment' | 'penthouse' | 'shop' | 'office' | 'parking';

export interface ApartmentUnit extends FinancialApproval {
  id: string;
  projectId: string;
  unitNumber: string;
  floor: number;
  buildingSection?: string;
  unitType?: UnitType;
  type?: string;
  areaM2: number;
  rooms?: number;
  pricePerM2?: number;
  totalPrice?: number;
  salePrice?: number;
  currency?: 'USD' | 'AFN' | string;
  exchangeRate?: number;
  salePriceInAFN?: number;
  salePriceInUSD?: number;
  status: UnitStatus;
  buyerName?: string;
  buyerPhone?: string;
  buyerTazkira?: string;
  buyerDetails?: string;
  contractDate?: string;
  saleDate?: string;
  downPayment?: number;
  downPaymentCurrency?: 'USD' | 'AFN' | string;
  amountReceived?: number;
  totalReceived?: number;
  remainingBalance?: number;
  payments?: any[];
  installments?: InstallmentRecord[];
  areaSqm?: number;
  roomsCount?: number;
  totalPriceUSD?: number;
  downPaymentUSD?: number;
  paidAmountUSD?: number;
  notes?: string;
  createdAt: string;
}

export type InstallmentStatus = 'Pending' | 'Due' | 'Partially Paid' | 'Paid' | 'Overdue';

export interface InstallmentPaymentRecord {
  id: string;
  paymentId?: string;
  paymentNumber?: number;
  amount: number;
  currency: string;
  date: string;
  receiptNumber?: string;
  notes?: string;
  receivedBy?: string;
}

export interface InstallmentRecord {
  id: string;
  installmentNumber: number;
  buyerName?: string;
  buyerPhone?: string;
  apartmentNumber?: string;
  apartmentId?: string;
  projectId?: string;
  amount: number;
  dueDate: string;
  paidAmount: number;
  remainingAmount: number;
  currency?: string;
  status: InstallmentStatus;
  paymentHistory: InstallmentPaymentRecord[];
  notes?: string;
  // Backward compatibility fields
  date?: string;
  received?: boolean;
  receivedDate?: string;
}

export type NotificationType = 
  | 'installment_due_soon' 
  | 'installment_overdue' 
  | 'installment_paid'
  | 'unpaid_purchase' 
  | 'outstanding_debt' 
  | 'missing_document' 
  | 'project_inactivity'
  | 'general_financial';

export interface SystemReminderNotification {
  id: string;
  reminderKey: string; // Used for deduplication
  projectId?: string;
  projectName?: string;
  apartmentId?: string;
  apartmentNumber?: string;
  installmentId?: string;
  installmentNumber?: number;
  expenseId?: string;
  paymentId?: string;
  type: NotificationType;
  title: string;
  message: string;
  date: string; // YYYY-MM-DD
  time: string; // HH:MM
  read: boolean;
  dismissed: boolean;
  priority: 1 | 2 | 3 | 4 | 5 | 6 | 7; // 1 = highest (overdue installments)
  actionTab?: string;
  actionPayload?: any;
}

export interface FixedAsset {
  id: string;
  assetTag: string;
  name: string;
  category: 'machinery' | 'vehicle' | 'equipment' | 'building' | 'it';
  assignedProjectId?: string;
  purchaseDate: string;
  purchaseCost: number;
  salvageValue: number;
  usefulLifeYears: number;
  monthlyDepreciation: number;
  accumulatedDepreciation: number;
  currentBookValue: number;
  runningHours?: number;
  hourlyOperatingRate?: number;
  serialNumber?: string;
  assignedPerson?: string;
  status: 'active' | 'maintenance' | 'idle' | 'disposed';
  notes?: string;
  createdAt?: string;
}

/* ------------------------------------------------------------------------ */
/* Accounting / Chart of Accounts / Journal                                  */
/*                                                                           */
/* Journal entries are DERIVED from operational records (expenses, steel,    */
/* concrete, payments, apartment sales, partner investments) by the journal  */
/* engine (`src/utils/journalEngine.ts`). They are a pure projection — never */
/* edited directly, never out of sync with the underlying business data.     */
/* ------------------------------------------------------------------------ */

export type JournalSourceType =
  | 'expense'
  | 'steel'
  | 'concrete'
  | 'payment'
  | 'apartment_sale'
  | 'investment'
  | 'adjustment'
  | 'reversal'
  | 'transfer' | 'credit_note';

export interface JournalLine {
  /** 4-digit account code from the Chart of Accounts */
  accountCode: string;
  /**
   * Phase 3 (WIP): when a construction cost line is capitalized into 1500
   * WIP, this field preserves the natural cost classification (e.g. '5100'
   * Steel) so cost-detail reports stay possible alongside the functional
   * WIP/COGS presentation.
   */
  costAccountCode?: string;
  /** Amounts in the entry's original currency */
  debit: number;
  credit: number;
  /** Normalized amounts (entry exchange rate) for cross-currency reporting */
  debitUSD: number;
  creditUSD: number;
  debitAFN: number;
  creditAFN: number;
  memo?: string;
}

export interface JournalEntry {
  reversalOf?: string;
  /** Deterministic id: `je-{sourceType}-{sourceId}` (+ suffix when needed) */
  id: string;
  /** YYYY-MM-DD */
  date: string;
  time?: string;
  projectId?: string;
  description: string;
  sourceType: JournalSourceType;
  /** Id of the business record this entry was derived from */
  sourceId: string;
  currency: string;
  exchangeRate: number;
  lines: JournalLine[];
  /** Sum of line debits/credits (normalized) — used for balance checks */
  totalDebitUSD: number;
  totalCreditUSD: number;
}

export interface AccountBalance {
  accountCode: string;
  nameEn: string;
  nameFa: string;
  namePs: string;
  accountType: 'asset' | 'liability' | 'equity' | 'revenue' | 'expense';
  isHeader: boolean;
  /** Total debits minus credits, in account-normal sign convention */
  balanceUSD: number;
  balanceAFN: number;
  netUSD?: number;
  netAFN?: number;
  debitUSD: number;
  creditUSD: number;
  debitAFN: number;
  creditAFN: number;
  entryCount: number;
}

export interface TrialBalanceRow {
  accountCode: string;
  nameEn: string;
  nameFa: string;
  namePs: string;
  debitUSD: number;
  creditUSD: number;
}

/* ------------------------------------------------------------------------ */
/* Project Budget (per-project, per-account)                                 */
/*                                                                           */
/* One budget line per (projectId, accountCode). The accountCode links the   */
/* budget directly into the Chart of Accounts — no parallel/duplicate        */
/* category system. Actual spending is derived live from purchases           */
/* (expenses / steel / concrete) via the same category→account mapping the   */
/* journal engine uses, so Budget vs Actual can never disagree by mapping.   */
/* ------------------------------------------------------------------------ */

export interface ProjectBudget {
  id: string;
  projectId: string;
  /** 4-digit COA code (normally a 5xxx project-cost account) */
  accountCode: string;
  amount: number;
  currency: string;
  exchangeRate?: number;
  amountUSD?: number;
  amountAFN?: number;
  notes?: string;
  createdAt: string;
  updatedAt?: string;
  createdBy?: string;
}

export type BudgetStatus = 'normal' | 'warning' | 'over' | 'unbudgeted';

export interface BudgetLineReport {
  accountCode: string;
  nameEn: string;
  nameFa: string;
  namePs: string;
  /** Whether an explicit budget exists for this account on this project */
  hasBudget: boolean;
  budgetUSD: number;
  /** Committed cost = purchases recorded (accrual basis) */
  committedUSD: number;
  /** Portion of the committed cost actually settled in cash */
  paidUSD: number;
  payableUSD: number;
  remainingUSD: number;
  /** committed/budget*100 — null when no budget exists */
  usagePct: number | null;
  status: BudgetStatus;
}

export type Apartment = ApartmentUnit;

export type PaymentMethod = 'Cash' | 'Bank Transfer' | 'Hawala / Sarafi' | 'Hawala / Sarrafi' | 'Cheque';
export type PaymentRelatedType =
  | 'payroll'
 
  | 'contractor' 
  | 'supplier' 
  | 'material_steel' 
  | 'material_concrete' 
  | 'expense' | 'retention_release' | 'tax_remittance' 
  | 'steel' 
  | 'concrete' 
  | 'apartment_sale' 
  | 'client_advance' | 'other';

export interface Payment extends FinancialApproval {
  installmentId?: string;
  generatedDescription?: 'installment'|'downPayment';
  id: string;
  projectId: string;
  date: string;
  time?: string;
  amount: number;
  currency: string;
  exchangeRate?: number;
  amountInUSD?: number;
  amountInAFN?: number;
  amountUSD?: number;
  amountAFN?: number;
  method: PaymentMethod;
  paymentMethod?: string;
  paymentType?: 'income' | 'expense';
  relatedType: PaymentRelatedType;
  relatedId?: string;
  partyName: string;
  recipientName?: string;
  recipientId?: string;
  recipientType?: string;
  receiptNumber?: string;
  referenceNumber?: string;
  bankOrSarafiName?: string;
  description: string;
  notes?: string;
  documentUrl?: string;
  createdAt: string;
  createdBy: string;
}

export type DocumentCategory = 
  | 'permits'
  | 'drawings'
  | 'lab_reports'
  | 'contracts'
  | 'invoices'
  | 'sales_contracts'
  | 'legal'
  | 'bill'
  | 'receipt'
  | 'photo'
  | 'backup'
  | 'other'
  | string;

export interface DocumentRecord {
  id: string;
  projectId: string;
  title: string;
  category: DocumentCategory;
  fileUrl: string;
  fileName?: string;
  fileType?: string;
  fileSize?: string;
  relatedType?: string;
  relatedId?: string;
  amount?: number;
  date: string;
  googleDriveFileId?: string;
  googleDriveLink?: string;
  notes?: string;
  createdAt: string;
}

export type DocumentAttachment = DocumentRecord;

/** دفتر هفت usage telemetry: what each user touched and when */
export interface UserActivityEvent {
  id: string;
  userId: string;
  userName: string;
  role: UserRole;
  kind: 'login' | 'logout' | 'feature';
  /** tab/section id for 'feature' kind */
  feature?: string;
  label?: string;
  timestamp: string; // ISO
}

export interface AuditLog {
  id: string;
  projectId?: string;
  projectName?: string;
  userId?: string;
  userName: string;
  date?: string;
  time?: string;
  timestamp?: string;
  action: 'create' | 'update' | 'delete' | 'login' | string;
  entityType: 'expense' | 'steel' | 'concrete' | 'contractor' | 'supplier' | 'apartment' | 'payment' | 'project' | 'user' | string;
  entityId?: string;
  entityName?: string;
  recordTitle: string;
  previousValue?: any;
  newValue?: any;
  oldValue?: any;
  details: string;
}

export interface ProjectFinancialSummary {
  totalExpenses: number;
  totalPayments: number;
  totalOutstanding: number;
  totalSales: number;
  totalSalesReceived: number;
  salesOutstanding: number;
  netPosition: number;
  steelTotal: number;
  steelPaid: number;
  steelRemaining: number;
  concreteTotal: number;
  concretePaid: number;
  concreteRemaining: number;
  contractorTotal: number;
  contractorPaid: number;
  contractorRemaining: number;
  supplierTotal: number;
  supplierPaid: number;
  supplierRemaining: number;
  expensesUSD?: number;
  expensesAFN?: number;
  paymentsUSD?: number;
  paymentsAFN?: number;
  outstandingUSD?: number;
  outstandingAFN?: number;
  salesUSD?: number;
  salesAFN?: number;
  salesReceivedUSD?: number;
  salesReceivedAFN?: number;
  salesOutstandingUSD?: number;
  salesOutstandingAFN?: number;
  netPositionUSD?: number;
  netPositionAFN?: number;
  steelUSD?: number;
  steelAFN?: number;
  concreteUSD?: number;
  concreteAFN?: number;
  contractorUSD?: number;
  contractorAFN?: number;
  supplierUSD?: number;
  supplierAFN?: number;
}

export interface LaborRecord extends FinancialApproval {
  currency?: string;
  exchangeRate?: number;
  date?: string;
  id: string;
  projectId: string;
  workerName: string;
  role: string;
  phone?: string;
  workPeriod: string;
  daysWorked: number;
  dailyRate: number;
  grossWage: number;
  advanceDeduction: number;
  netPayable: number;
  paymentStatus: 'paid' | 'pending' | 'partial';
  paidDate?: string;
  approvedBy?: string;
  notes?: string;
  createdAt?: string;
}


export interface ProjectLedgerTransfer extends FinancialApproval {
 id:string;projectId:string;toProjectId?:string;date:string;kind:'cash_transfer'|'material_transfer'|'ownership_transfer';amount:number;currency:string;exchangeRate:number;method:string;toMethod:string;accountCode?:string;materialName?:string;quantity?:number;unit?:string;previousOwner?:string;newOwner?:string;notes?:string;createdAt:string;
}

export interface CreditNote extends FinancialApproval {daysReduction?:number;id:string;projectId:string;sourceCollection:string;sourceId:string;date:string;amount:number;currency:string;exchangeRate:number;reason:string;createdAt:string;}
