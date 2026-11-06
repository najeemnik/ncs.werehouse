import {saveLocalFile} from './localFiles';
/**
 * NSC Backend API Service
 * Secure REST client connecting React with the Shahhost MySQL/PHP Backend
 * Fully compatible with Offline-First caching and Office 7 Master Gate
 */

const API_BASE_URL = import.meta.env.VITE_API_URL || '/api';

export interface ApiResponse<T = any> {
  success: boolean;
  data?: T;
  error?: string;
}

class BackendApiService {
  private csrf: string | null = null;
  private workspaceCompany: string | null = null;
  public setWorkspaceCompany(id:string|null) {this.workspaceCompany=id;}
  private workspaceEndpoint() {return 'workspace.php'+(this.workspaceCompany?'?company='+encodeURIComponent(this.workspaceCompany):'');}
  public studio(action='config',payload?:any,project?:string) {const scope=this.workspaceCompany?'&company='+encodeURIComponent(this.workspaceCompany):'';return this.request<any>('studio.php?action='+encodeURIComponent(action)+scope+(project!==undefined?'&project='+encodeURIComponent(project):''),payload?{method:'POST',body:JSON.stringify({...payload,action})}:{});}
  public studioRecords(module:string,project:string,offset=0) {return this.request<any>('studio.php?action=records&module='+encodeURIComponent(module)+'&project='+encodeURIComponent(project)+'&offset='+offset+(this.workspaceCompany?'&company='+encodeURIComponent(this.workspaceCompany):''));}
  public studioVersion(version:number) {return this.request<any>('studio.php?action=version&version='+version+(this.workspaceCompany?'&company='+encodeURIComponent(this.workspaceCompany):''));}
  public setToken(_token: string | null) { localStorage.removeItem('nsc_auth_token'); }
  public setOffice7GateKey(_key: string | null) { localStorage.removeItem('nsc_office7_key'); }
  private async request<T>(endpoint: string, options: RequestInit = {}): Promise<ApiResponse<T>> {
    if (import.meta.env.VITE_PREVIEW === 'true') return {success:true};
    const headers: Record<string,string> = {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest',...(options.headers as Record<string,string> || {})};
    if(this.csrf) headers['X-CSRF-Token']=this.csrf;
    try {
      const response=await fetch(`${API_BASE_URL}/${endpoint}`,{...options,headers,credentials:'same-origin'});
      const json=await response.json();
      if(!response.ok || json.success!==true) {
        const error=Object.assign(new Error(json.error || `Server error (${response.status})`),{status:response.status,code:json.data?.code});
        window.dispatchEvent(new CustomEvent('nsc-api-error',{detail:{message:error.message,status:response.status,code:error.code}}));
        throw error;
      }
      return json;
    } catch(error: any) {
      if(!error.status) window.dispatchEvent(new CustomEvent('nsc-api-error',{detail:{message:'Unable to save. Check your connection and retry.'}}));
      throw error;
    }
  }
  // --- احراز هویت (Auth) ---
  public async login(username: string, password: string) {
    try{await this.getCurrentUser();}catch(error:any){if(error.status===401||['licenseExpired','companyLocked','accountLocked'].includes(error.code))await this.getCurrentUser();else throw error;}
    const res=await this.request<{user:any;csrf:string}>('auth.php?action=login',{method:'POST',body:JSON.stringify({username,password})});
    this.csrf=res.data?.csrf || null;
    return res;
  }
  public async getCurrentUser() {
    const res=await this.request<{user:any;csrf:string}>('auth.php');
    this.csrf=res.data?.csrf || null;
    return res;
  }
  public async logout() {
    try {await this.request('auth.php?action=logout',{method:'POST',body:'{}'});} finally {this.csrf=null;this.setToken(null);this.setOffice7GateKey(null);}
  }
  public async requestPasswordReset(email:string) { await this.getCurrentUser();return this.request('auth.php?action=request_reset',{method:'POST',body:JSON.stringify({email})}); }
  public async completePasswordReset(token:string,password:string) { await this.getCurrentUser();return this.request('auth.php?action=complete_reset',{method:'POST',body:JSON.stringify({token,password})}); }
  public async publicPage(){if(import.meta.env.VITE_PREVIEW==='true')return {success:true,data:{content:JSON.parse(localStorage.getItem('nsc_public_page')||'{}'),revision:0}};return this.request<{content:any;revision:number}>('public.php');}
  public async savePublicPage(content:any,revision:number){if(import.meta.env.VITE_PREVIEW==='true'){localStorage.setItem('nsc_public_page',JSON.stringify(content));return {success:true,data:{revision:revision+1}};}return this.request<{revision:number}>('public.php',{method:'POST',body:JSON.stringify({content,revision})});}
  public async listUsers() { const res=await this.request<{users:any[]}>('users.php');return res.data!.users; }
  public async saveUser(payload:any) {const res=await this.request<{user:any}>('users.php',{method:'POST',body:JSON.stringify(payload)});return res.data!.user;}
  public async disableUser(id:string) {return this.request('users.php',{method:'POST',body:JSON.stringify({action:'disable',id})});}
  public async uploadDocument(file:File,projectId:string) {
    if(import.meta.env.VITE_PREVIEW==='true')return saveLocalFile(file);
    const form=new FormData();form.append('file',file);form.append('projectId',projectId);
    const response=await fetch(`${API_BASE_URL}/files.php${this.workspaceCompany?'?company='+encodeURIComponent(this.workspaceCompany):''}`,{method:'POST',credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest','X-CSRF-Token':this.csrf||''},body:form});
    const res=await response.json();if(!response.ok||!res.success)throw new Error(res.error||'Upload failed.');return res.data as {url:string;id:string};
  }
  public async uploadProjectImage(data:string,projectId:string,kind:string){const match=data.match(/^data:(image\/(?:jpeg|png|webp));base64,([A-Za-z0-9+/=]+)$/);if(!match)throw Error('Invalid image');const bytes=Uint8Array.from(atob(match[2]),c=>c.charCodeAt(0)),blob=new Blob([bytes],{type:match[1]}),form=new FormData();form.append('file',new File([blob],'project-image',{type:blob.type}));form.append('projectId',projectId);form.append('kind',kind);const r=await fetch(`${API_BASE_URL}/media.php${this.workspaceCompany?'?company='+encodeURIComponent(this.workspaceCompany):''}`,{method:'POST',credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest','X-CSRF-Token':this.csrf||''},body:form});const json=await r.json();if(!r.ok||!json.success)throw Error(json.error||'Upload failed');return json.data.url as string;}
  public async loadWorkspace() {
    const res=await this.request<{revision:number;document:Record<string,any>|null}>(this.workspaceEndpoint());
    return res.data!;
  }
  public async validateLayout(design:unknown){return this.request(this.workspaceEndpoint(),{method:'POST',body:JSON.stringify({action:'validate_design',design})});}
  public async saveWorkspace(document:Record<string,unknown>,revision:number) {
    const res=await this.request<{revision:number;document:Record<string,any>}>(this.workspaceEndpoint(),{method:'PUT',body:JSON.stringify({revision,document})});
    return res.data!;
  }
  public async transitionApproval(collection:string,id:string,status:string,revision:number,details?:{date:string;reason:string;amount?:number}) {
    const res=await this.request<{revision:number;document:Record<string,any>}>(this.workspaceEndpoint(),{method:'POST',body:JSON.stringify({collection,id,status,revision,...(details||{})})});return res.data!;
  }
  // --- دفتر هفت (Office 7 Master Control) ---
  public async updateOffice7License(payload:any){return this.request<{user:any}>('office7.php?action=update_license',{method:'POST',body:JSON.stringify(payload)});}
  public async setOffice7Lock(payload:any){return this.request<{user:any}>('office7.php?action=toggle_lock',{method:'POST',body:JSON.stringify(payload)});}
  public async updateOfficeUser(payload:any){return this.request<{user:any}>('office-users.php',{method:'POST',body:JSON.stringify(payload)});}
  public async activityHistory(project:string){return this.request<{logs:any[]}>('audit.php?project='+encodeURIComponent(project));}
  public async getOffice7Tenants() {
    return this.request<{ users: any[] }>('office7.php?action=tenants');
  }







  public async getAuditLogs(limit = 100) {
    return this.request<{ logs: any[] }>(`office7.php?action=audit_logs&limit=${limit}`);
  }

  // --- پروژه‌ها و بودجه (Projects & Budgets) ---
  public async getProjects() {
    return this.request<{ projects: any[] }>('projects.php');
  }

  public async getProjectDetails(id: string) {
    return this.request<{ project: any; budgets: any[]; actualCosts: any[]; totalRevenue: number }>(`projects.php?id=${id}`);
  }

  public async saveProject(project: any) {
    return this.request('projects.php?action=save_project', {
      method: 'POST',
      body: JSON.stringify(project),
    });
  }

  public async saveBudget(budget: any) {
    return this.request('projects.php?action=save_budget', {
      method: 'POST',
      body: JSON.stringify(budget),
    });
  }

  // --- عملیات مالی (Finance & Treasury) ---
  public async getTreasuryAccounts() {
    return this.request<{ accounts: any[] }>('finance.php?section=treasury_accounts');
  }

  public async getIncomes(projectId?: string) {
    const q = projectId ? `&projectId=${projectId}` : '';
    return this.request<{ incomes: any[] }>(`finance.php?section=incomes${q}`);
  }

  public async createIncome(payload: any) {
    payload = {...payload,requestId:payload.requestId || crypto.randomUUID()};
    return this.request('finance.php?action=create_income', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  public async getPayments(projectId?: string) {
    const q = projectId ? `&projectId=${projectId}` : '';
    return this.request<{ payments: any[] }>(`finance.php?section=payments${q}`);
  }

  public async createPayment(payload: any) {
    payload = {...payload,requestId:payload.requestId || crypto.randomUUID()};
    return this.request('finance.php?action=create_payment', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  public async interProjectTransfer(payload: any) {
    payload = {...payload,requestId:payload.requestId || crypto.randomUUID()};
    return this.request('finance.php?action=inter_project_transfer', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  // --- تدارکات مواد (Materials) ---
  public async getMaterials(projectId?: string) {
    const q = projectId ? `?projectId=${projectId}` : '';
    return this.request<{ materials: any[] }>(`materials.php${q}`);
  }

  public async createMaterialProcurement(payload: any) {
    return this.request('materials.php', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  // --- معاشات و کارکرد کارگران (Labor & Wages) ---
  public async getLaborPayroll(projectId?: string) {
    const q = projectId ? `?projectId=${projectId}` : '';
    return this.request<{ payroll: any[] }>(`payroll.php${q}`);
  }

  public async createLaborPayroll(payload: any) {
    return this.request('payroll.php', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  // --- گزارشات مدیریتی و P&L (Reports) ---
  public async getDashboardMetrics() {
    return this.request<any>('reports.php?type=dashboard');
  }

  // --- مقاطعه‌کاران (Subcontractors) ---
  public async getSubcontractors(projectId?: string) {
    const q = projectId ? `?projectId=${projectId}` : '';
    return this.request<{ subcontractors: any[] }>(`subcontractors.php${q}`);
  }

  public async saveSubcontractor(payload: any) {
    return this.request('subcontractors.php', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  // --- عرضه‌کنندگان (Suppliers) ---
  public async getSuppliers() {
    return this.request<{ suppliers: any[] }>('suppliers.php');
  }

  public async saveSupplier(payload: any) {
    return this.request('suppliers.php', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  // --- پلاک‌ها و اپارتمان‌ها (Apartments) ---
  public async getApartmentUnits(projectId?: string) {
    const q = projectId ? `?projectId=${projectId}` : '';
    return this.request<{ units: any[] }>(`apartments.php${q}`);
  }

  public async saveApartmentUnit(payload: any) {
    return this.request('apartments.php', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  // --- اسناد روزنامچه و حسابداری دوطرفه (Journal & Chart of Accounts) ---
  public async getChartOfAccounts() {
    return this.request<{ accounts: any[] }>('journal.php?action=chart_of_accounts');
  }

  public async getJournalEntries() {
    return this.request<{ entries: any[] }>('journal.php?action=entries');
  }

  public async createJournalEntry(payload: any) {
    return this.request('journal.php', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  // --- ماشین‌آلات و دارایی‌ها (Assets & Equipment) ---
  public async getAssets(projectId?: string) {
    const q = projectId ? `?projectId=${projectId}` : '';
    return this.request<{ assets: any[] }>(`assets.php${q}`);
  }

  public async saveAsset(payload: any) {
    return this.request('assets.php', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  public async recordAsset(payload: any) {
    return this.saveAsset(payload);
  }

  public async getProjectPnl() {
    return this.request<{ pnl: any[] }>('reports.php?type=project_pnl');
  }

  public async getBudgetVsActual(projectId: string) {
    return this.request<{ comparison: any[] }>(`reports.php?type=budget_vs_actual&projectId=${projectId}`);
  }

  public async getMonthlyTimeline(year?: number) {
    const y = year || new Date().getFullYear();
    return this.request<{ year: number; months: any[] }>(`reports.php?type=monthly_timeline&year=${y}`);
  }
}

export const backendApi = new BackendApiService();
