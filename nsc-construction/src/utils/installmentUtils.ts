import { 
  ApartmentUnit, 
  InstallmentRecord, 
  InstallmentStatus, 
  SystemReminderNotification,
  Project,
  Expense,
  Payment,
  Contractor,
  Supplier,
  DocumentRecord
} from '../types';

/**
 * Calculates current InstallmentStatus based on paid amounts, total amount and due date.
 */
export function calculateInstallmentStatus(
  amount: number,
  paidAmount: number,
  dueDateStr: string,
  now: Date = new Date()
): InstallmentStatus {
  const remaining = Math.max(0, amount - paidAmount);
  if (remaining <= 0.001) {
    return 'Paid';
  }

  // Parse due date cleanly
  const due = new Date(dueDateStr);
  // Reset time to start of day for accurate day comparisons
  const dueStartOfDay = new Date(due.getFullYear(), due.getMonth(), due.getDate()).getTime();
  const nowStartOfDay = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();

  if (nowStartOfDay > dueStartOfDay) {
    return 'Overdue';
  }

  if (nowStartOfDay === dueStartOfDay) {
    return 'Due';
  }

  if (paidAmount > 0) {
    return 'Partially Paid';
  }

  return 'Pending';
}

/**
 * Calculates days difference (positive if overdue, negative if days remaining until due).
 */
export function getDaysDifference(dueDateStr: string, now: Date = new Date()): number {
  const due = new Date(dueDateStr);
  const dueStartOfDay = new Date(due.getFullYear(), due.getMonth(), due.getDate()).getTime();
  const nowStartOfDay = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
  const diffMs = nowStartOfDay - dueStartOfDay;
  return Math.round(diffMs / (1000 * 60 * 60 * 24));
}

/**
 * Normalizes an apartment's installments array. If missing but apartment is sold with remaining balance,
 * auto-generates structured InstallmentRecord entries based on salePrice, downPayment and remaining balance.
 */
export function normalizeApartmentInstallments(apartment: ApartmentUnit, now: Date = new Date()): InstallmentRecord[] {
  if (apartment.installments && apartment.installments.length > 0) {
    return apartment.installments.map((inst, idx) => {
      const instNumber = inst.installmentNumber || (idx + 1);
      const paid = inst.paidAmount !== undefined 
        ? inst.paidAmount 
        : (inst.received ? inst.amount : 0);
      const remaining = Math.max(0, inst.amount - paid);
      const dueDate = inst.dueDate || inst.date || apartment.saleDate || new Date().toISOString().split('T')[0];
      const status = calculateInstallmentStatus(inst.amount, paid, dueDate, now);

      return {
        id: inst.id || `inst-${apartment.id}-${instNumber}`,
        installmentNumber: instNumber,
        buyerName: inst.buyerName || apartment.buyerName,
        buyerPhone: inst.buyerPhone || apartment.buyerPhone,
        apartmentNumber: inst.apartmentNumber || apartment.unitNumber,
        apartmentId: inst.apartmentId || apartment.id,
        projectId: inst.projectId || apartment.projectId,
        amount: inst.amount,
        dueDate,
        paidAmount: paid,
        remainingAmount: remaining,
        currency: inst.currency || apartment.currency || 'USD',
        status,
        paymentHistory: inst.paymentHistory || (paid > 0 ? [{
          id: `payhist-${inst.id || instNumber}-1`,
          paymentNumber: 1,
          amount: paid,
          currency: inst.currency || apartment.currency || 'USD',
          date: inst.receivedDate || inst.date || dueDate,
          receiptNumber: `REC-${instNumber}`,
          notes: 'پرداخت قبلی ثبت شده در سیستم'
        }] : []),
        notes: inst.notes || '',
        date: dueDate,
        received: remaining === 0,
        receivedDate: inst.receivedDate
      };
    });
  }

  // If no structured installments exist, but unit is sold
  if (apartment.status === 'sold' && (apartment.salePrice || 0) > 0) {
    const salePrice = apartment.salePrice || apartment.totalPriceUSD || 0;
    const downPayment = apartment.downPayment || apartment.downPaymentUSD || 0;
    const amountReceived = apartment.amountReceived || apartment.paidAmountUSD || downPayment;
    const remainingBalance = Math.max(0, salePrice - amountReceived);

    const generated: InstallmentRecord[] = [];
    const baseDate = new Date(apartment.saleDate || apartment.createdAt || '2024-05-01');

    // If there is downpayment / initial amount
    if (downPayment > 0) {
      generated.push({
        id: `inst-${apartment.id}-down`,
        installmentNumber: 1,
        buyerName: apartment.buyerName,
        buyerPhone: apartment.buyerPhone,
        apartmentNumber: apartment.unitNumber,
        apartmentId: apartment.id,
        projectId: apartment.projectId,
        amount: downPayment,
        dueDate: baseDate.toISOString().split('T')[0],
        paidAmount: downPayment,
        remainingAmount: 0,
        currency: apartment.currency || 'USD',
        status: 'Paid',
        paymentHistory: [{
          id: `payhist-${apartment.id}-dp`,
          paymentNumber: 1,
          amount: downPayment,
          currency: apartment.currency || 'USD',
          date: baseDate.toISOString().split('T')[0],
          receiptNumber: `DP-${apartment.unitNumber}`,
          notes: 'پیش‌پرداخت عقد قرارداد'
        }],
        notes: 'پیش‌پرداخت قرارداد',
        date: baseDate.toISOString().split('T')[0],
        received: true,
        receivedDate: baseDate.toISOString().split('T')[0]
      });
    }

    // Next installments based on remaining
    if (remainingBalance > 0) {
      // Split into 2 or 3 scheduled installments
      const instCount = remainingBalance > 50000 ? 3 : 2;
      const portion = Math.round(remainingBalance / instCount);
      let runningPaid = Math.max(0, amountReceived - downPayment);

      for (let i = 1; i <= instCount; i++) {
        const instAmount = i === instCount ? (remainingBalance - (portion * (instCount - 1))) : portion;
        const instDueDate = new Date(baseDate.getTime() + (i * 45 * 24 * 60 * 60 * 1000)).toISOString().split('T')[0];
        
        let thisPaid = 0;
        if (runningPaid >= instAmount) {
          thisPaid = instAmount;
          runningPaid -= instAmount;
        } else if (runningPaid > 0) {
          thisPaid = runningPaid;
          runningPaid = 0;
        }

        const remaining = Math.max(0, instAmount - thisPaid);
        const status = calculateInstallmentStatus(instAmount, thisPaid, instDueDate, now);

        generated.push({
          id: `inst-${apartment.id}-${i + 1}`,
          installmentNumber: i + 1,
          buyerName: apartment.buyerName,
          buyerPhone: apartment.buyerPhone,
          apartmentNumber: apartment.unitNumber,
          apartmentId: apartment.id,
          projectId: apartment.projectId,
          amount: instAmount,
          dueDate: instDueDate,
          paidAmount: thisPaid,
          remainingAmount: remaining,
          currency: apartment.currency || 'USD',
          status,
          paymentHistory: thisPaid > 0 ? [{
            id: `payhist-${apartment.id}-${i + 1}-1`,
            paymentNumber: 1,
            amount: thisPaid,
            currency: apartment.currency || 'USD',
            date: instDueDate,
            receiptNumber: `REC-${apartment.unitNumber}-${i + 1}`,
            notes: 'پرداخت قسمتی از قسط'
          }] : [],
          notes: `قسط شماره ${i + 1} اپارتمان ${apartment.unitNumber}`,
          date: instDueDate,
          received: remaining === 0,
          receivedDate: remaining === 0 ? instDueDate : undefined
        });
      }
    }

    return generated;
  }

  return [];
}

/**
 * Intelligent Smart Daily & Installment Reminders Engine
 * Evaluates real database entries:
 * 1. Overdue Installments (Daily, max 4 reminders/day, days overdue)
 * 2. Due Soon Installments (Thursday reminders, max 4, future due date, days until due)
 * 3. Unpaid Purchases / Expenses
 * 4. Outstanding contractor / supplier debt
 * 5. Missing critical documents
 * 6. Deduplication by unique reminderKey + date
 */
export function generateSmartReminders(params: {
  apartments: ApartmentUnit[];
  expenses: Expense[];
  payments: Payment[];
  contractors: Contractor[];
  suppliers: Supplier[];
  documents: DocumentRecord[];
  projects: Project[];
  existingNotifications: SystemReminderNotification[];
  now?: Date;
  formatCurrency: (val: number, cur?: string) => string;
}): SystemReminderNotification[] {
  const {
    apartments,
    expenses,
    contractors,
    suppliers,
    documents,
    projects,
    existingNotifications,
    now = new Date(),
    formatCurrency
  } = params;

  const todayStr = now.toISOString().split('T')[0];
  const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
  // In JavaScript: 0 = Sun, 1 = Mon, 2 = Tue, 3 = Wed, 4 = Thursday, 5 = Fri, 6 = Sat
  const isThursday = now.getDay() === 4;

  const newReminders: SystemReminderNotification[] = [];
  const existingKeys = new Set(existingNotifications.map(n => n.reminderKey));

  // --- 1. OVERDUE INSTALLMENTS (Priority 1) ---
  // Every day: max 4 reminders for overdue installments
  const overdueInstallments: Array<{ inst: InstallmentRecord; apt: ApartmentUnit; daysOverdue: number }> = [];

  apartments.forEach(apt => {
    const insts = normalizeApartmentInstallments(apt, now);
    insts.forEach(inst => {
      if (inst.status === 'Overdue' && inst.remainingAmount > 0) {
        const daysOverdue = Math.max(1, getDaysDifference(inst.dueDate, now));
        overdueInstallments.push({ inst, apt, daysOverdue });
      }
    });
  });

  // Sort by longest overdue & highest remaining
  overdueInstallments.sort((a, b) => b.daysOverdue - a.daysOverdue || b.inst.remainingAmount - a.inst.remainingAmount);

  // Take maximum 4 for today
  let overdueCount = 0;
  for (const item of overdueInstallments) {
    if (overdueCount >= 4) break;
    const reminderKey = `inst-overdue-${item.inst.id}-${todayStr}`;
    
    // Deduplication: only if not already created today
    if (!existingKeys.has(reminderKey)) {
      const cur = item.inst.currency || item.apt.currency || 'USD';
      const proj = projects.find(p => p.id === item.apt.projectId);
      const buyer = item.inst.buyerName || item.apt.buyerName || 'خریدار';
      const aptNum = item.inst.apartmentNumber || item.apt.unitNumber;

      newReminders.push({
        id: `rem-overdue-${item.inst.id}-${Date.now()}-${overdueCount}`,
        reminderKey,
        projectId: item.apt.projectId,
        projectName: proj?.name || 'پروژه ساختمانی',
        apartmentId: item.apt.id,
        apartmentNumber: aptNum,
        installmentId: item.inst.id,
        installmentNumber: item.inst.installmentNumber,
        type: 'installment_overdue',
        title: `⚠️ تأخیر در پرداخت قسط #${item.inst.installmentNumber} (اپارتمان ${aptNum})`,
        message: `قسط شماره ${item.inst.installmentNumber} اپارتمان ${aptNum} (${buyer}) سررسید شده و هنوز ${formatCurrency(item.inst.remainingAmount, cur)} باقی مانده است. مدت تأخیر: ${item.daysOverdue} روز.`,
        date: todayStr,
        time: timeStr,
        read: false,
        dismissed: false,
        priority: 1,
        actionTab: 'apartments',
        actionPayload: { apartmentId: item.apt.id, installmentId: item.inst.id }
      });
      existingKeys.add(reminderKey);
      overdueCount++;
    }
  }

  // --- 2. THURSDAY DUE SOON INSTALLMENTS (Priority 2) ---
  // Every Thursday: for installments that are Due Soon (future due date), create max 4 reminders
  if (isThursday) {
    const dueSoonInstallments: Array<{ inst: InstallmentRecord; apt: ApartmentUnit; daysLeft: number }> = [];

    apartments.forEach(apt => {
      const insts = normalizeApartmentInstallments(apt, now);
      insts.forEach(inst => {
        if ((inst.status === 'Pending' || inst.status === 'Partially Paid' || inst.status === 'Due') && inst.remainingAmount > 0) {
          const daysDiff = getDaysDifference(inst.dueDate, now);
          // daysDiff <= 0 means today or in the future
          const daysUntil = -daysDiff;
          if (daysUntil >= 0 && daysUntil <= 30) {
            dueSoonInstallments.push({ inst, apt, daysLeft: daysUntil });
          }
        }
      });
    });

    // Sort by closest due date
    dueSoonInstallments.sort((a, b) => a.daysLeft - b.daysLeft);

    let dueSoonCount = 0;
    for (const item of dueSoonInstallments) {
      if (dueSoonCount >= 4) break;
      const reminderKey = `inst-duesoon-${item.inst.id}-${todayStr}`;

      if (!existingKeys.has(reminderKey)) {
        const cur = item.inst.currency || item.apt.currency || 'USD';
        const proj = projects.find(p => p.id === item.apt.projectId);
        const buyer = item.inst.buyerName || item.apt.buyerName || 'خریدار';
        const aptNum = item.inst.apartmentNumber || item.apt.unitNumber;

        newReminders.push({
          id: `rem-duesoon-${item.inst.id}-${Date.now()}-${dueSoonCount}`,
          reminderKey,
          projectId: item.apt.projectId,
          projectName: proj?.name || 'پروژه ساختمانی',
          apartmentId: item.apt.id,
          apartmentNumber: aptNum,
          installmentId: item.inst.id,
          installmentNumber: item.inst.installmentNumber,
          type: 'installment_due_soon',
          title: `🗓️ یادآوری قسط: قسط #${item.inst.installmentNumber} اپارتمان ${aptNum}`,
          message: `یادآوری قسط: قسط شماره ${item.inst.installmentNumber} اپارتمان ${aptNum} (${buyer}) به مبلغ باقی‌مانده ${formatCurrency(item.inst.remainingAmount, cur)} در تاریخ ${item.inst.dueDate} سررسید می‌شود (${item.daysLeft === 0 ? 'امروز' : `${item.daysLeft} روز باقی مانده`}).`,
          date: todayStr,
          time: timeStr,
          read: false,
          dismissed: false,
          priority: 2,
          actionTab: 'apartments',
          actionPayload: { apartmentId: item.apt.id, installmentId: item.inst.id }
        });
        existingKeys.add(reminderKey);
        dueSoonCount++;
      }
    }
  }

  // --- 3. UNPAID PURCHASES & EXPENSES (Priority 3) ---
  const unpaidPurchases = expenses.filter(e => (e.remainingAmount || 0) > 0 || e.paymentStatus === 'unpaid');
  if (unpaidPurchases.length > 0) {
    const topUnpaid = [...unpaidPurchases].sort((a, b) => (b.remainingAmount || b.amount) - (a.remainingAmount || a.amount))[0];
    const reminderKey = `unpaid-exp-${topUnpaid.id}-${todayStr}`;
    if (!existingKeys.has(reminderKey)) {
      const proj = projects.find(p => p.id === topUnpaid.projectId);
      const rem = topUnpaid.remainingAmount !== undefined ? topUnpaid.remainingAmount : topUnpaid.amount;
      newReminders.push({
        id: `rem-unpaid-exp-${topUnpaid.id}-${Date.now()}`,
        reminderKey,
        projectId: topUnpaid.projectId,
        projectName: proj?.name || 'پروژه',
        expenseId: topUnpaid.id,
        type: 'unpaid_purchase',
        title: `🧾 خریداری بدون پرداخت / باقی‌داری بل`,
        message: `خریداری "${topUnpaid.description || topUnpaid.item || 'مصرف ساحوی'}" مبلغ ${formatCurrency(rem, topUnpaid.currency || 'USD')} هنوز پرداخت نشده است.`,
        date: todayStr,
        time: timeStr,
        read: false,
        dismissed: false,
        priority: 3,
        actionTab: 'expenses',
        actionPayload: { expenseId: topUnpaid.id }
      });
      existingKeys.add(reminderKey);
    }
  }

  // --- 4. CONTRACTOR / SUPPLIER OUTSTANDING DEBT (Priority 4) ---
  const topContractorWithDebt = contractors
    .map(c => ({ contractor: c, remaining: Math.max(0, c.contractAmount - (c.totalPaid || 0)) }))
    .filter(c => c.remaining > 0)
    .sort((a, b) => b.remaining - a.remaining)[0];

  if (topContractorWithDebt) {
    const reminderKey = `cont-debt-${topContractorWithDebt.contractor.id}-${todayStr}`;
    if (!existingKeys.has(reminderKey)) {
      const proj = projects.find(p => p.id === topContractorWithDebt.contractor.projectId);
      newReminders.push({
        id: `rem-cont-debt-${topContractorWithDebt.contractor.id}-${Date.now()}`,
        reminderKey,
        projectId: topContractorWithDebt.contractor.projectId,
        projectName: proj?.name,
        type: 'outstanding_debt',
        title: `👷 باقی‌داری قرارداد کارشناس / قراردادی`,
        message: `قراردادی "${topContractorWithDebt.contractor.name}" مبلغ ${formatCurrency(topContractorWithDebt.remaining, 'USD')} باقی‌داری دارد.`,
        date: todayStr,
        time: timeStr,
        read: false,
        dismissed: false,
        priority: 4,
        actionTab: 'contractors',
        actionPayload: { contractorId: topContractorWithDebt.contractor.id }
      });
      existingKeys.add(reminderKey);
    }
  }

  // --- 5. MISSING DOCUMENTS & RECEIPTS (Priority 6) ---
  const highExpensesWithoutDoc = expenses.filter(e => e.amount > 2000 && !e.documentUrl && !e.billNumber);
  if (highExpensesWithoutDoc.length > 0) {
    const exp = highExpensesWithoutDoc[0];
    const reminderKey = `missing-doc-${exp.id}-${todayStr}`;
    if (!existingKeys.has(reminderKey)) {
      newReminders.push({
        id: `rem-missing-doc-${exp.id}-${Date.now()}`,
        reminderKey,
        projectId: exp.projectId,
        expenseId: exp.id,
        type: 'missing_document',
        title: `📎 سند یا فاکتور ناقص`,
        message: `مصرف به مبلغ ${formatCurrency(exp.amount, exp.currency || 'USD')} (${exp.description}) فاقد شماره بل یا عکس فاکتور ضمیمه است.`,
        date: todayStr,
        time: timeStr,
        read: false,
        dismissed: false,
        priority: 6,
        actionTab: 'expenses',
        actionPayload: { expenseId: exp.id }
      });
      existingKeys.add(reminderKey);
    }
  }

  return newReminders;
}
