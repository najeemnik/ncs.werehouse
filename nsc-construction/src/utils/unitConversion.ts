export interface UnitDefinition {
  id: string;
  symbol: string;
  nameFa: string;
  namePs: string;
  nameEn: string;
  category: 'weight' | 'volume' | 'area' | 'length' | 'count' | 'time' | 'variable';
  hasStandardConversion: boolean;
  standardTargetUnit?: string;
  standardMultiplier?: number;
  description?: string;
}

export const AVAILABLE_UNITS: UnitDefinition[] = [
  // Weight Units (Standard)
  {
    id: 'Ton',
    symbol: 'Ton',
    nameFa: 'تن (Ton)',
    namePs: 'ټن',
    nameEn: 'Ton',
    category: 'weight',
    hasStandardConversion: true,
    standardTargetUnit: 'kg',
    standardMultiplier: 1000,
    description: '1 Ton = 1,000 kg',
  },
  {
    id: 'kg',
    symbol: 'kg',
    nameFa: 'کیلوگرام (kg)',
    namePs: 'کیلوګرام',
    nameEn: 'Kilogram',
    category: 'weight',
    hasStandardConversion: true,
    standardTargetUnit: 'Ton',
    standardMultiplier: 0.001,
    description: '1,000 kg = 1 Ton',
  },
  {
    id: 'seer',
    symbol: 'سیر',
    nameFa: 'سیر کابل (Seer - 7kg)',
    namePs: 'کابل سیر',
    nameEn: 'Seer (7 kg)',
    category: 'weight',
    hasStandardConversion: true,
    standardTargetUnit: 'kg',
    standardMultiplier: 7,
    description: '1 سیر = 7 kg',
  },
  {
    id: 'kharwar',
    symbol: 'خروار',
    nameFa: 'خروار (Kharwar - 560kg)',
    namePs: 'خروار',
    nameEn: 'Kharwar (560 kg)',
    category: 'weight',
    hasStandardConversion: true,
    standardTargetUnit: 'kg',
    standardMultiplier: 560,
    description: '1 خروار = 80 سیر = 560 kg',
  },
  // Volume Units
  {
    id: 'm3',
    symbol: 'm³',
    nameFa: 'متر مکعب (m³)',
    namePs: 'مکعب متر',
    nameEn: 'Cubic Meter (m³)',
    category: 'volume',
    hasStandardConversion: false,
    description: 'واحد حجم کانکریت و خاک',
  },
  {
    id: 'liter',
    symbol: 'L',
    nameFa: 'لیتر (L)',
    namePs: 'لیتر',
    nameEn: 'Liter',
    category: 'volume',
    hasStandardConversion: false,
    description: 'مایعات، تیل و افزودنی',
  },
  // Area Units
  {
    id: 'm2',
    symbol: 'm²',
    nameFa: 'متر مربع (m²)',
    namePs: 'مربع متر',
    nameEn: 'Square Meter (m²)',
    category: 'area',
    hasStandardConversion: false,
    description: 'مساحت اپارتمان و کارگری',
  },
  // Length Units
  {
    id: 'meter',
    symbol: 'm',
    nameFa: 'متر طول (Meter)',
    namePs: 'متر',
    nameEn: 'Meter (m)',
    category: 'length',
    hasStandardConversion: true,
    standardTargetUnit: 'cm',
    standardMultiplier: 100,
    description: '1 meter = 100 cm',
  },
  // Count / Discrete Units
  {
    id: 'bag',
    symbol: 'bag',
    nameFa: 'خریطه / پاکت (Bag)',
    namePs: 'بوجۍ',
    nameEn: 'Bag (Cement / Gypsum)',
    category: 'variable',
    hasStandardConversion: false,
    description: 'سمنت و گچ (معمولاً ۵۰ کیلوگرام)',
  },
  {
    id: 'piece',
    symbol: 'pcs',
    nameFa: 'دانه / عدد (Piece)',
    namePs: 'دانې',
    nameEn: 'Piece / Count',
    category: 'count',
    hasStandardConversion: false,
    description: 'خشت، بلاک، چوب و لوازم برقی',
  },
  {
    id: 'truck',
    symbol: 'Truck',
    nameFa: 'موتر / لارۍ (Truck)',
    namePs: 'موټر',
    nameEn: 'Truck Load',
    category: 'variable',
    hasStandardConversion: false,
    description: 'موتر ریگ، جغله یا خاکبرداری',
  },
  {
    id: 'carton',
    symbol: 'box',
    nameFa: 'کارتن / کارتن (Carton / Box)',
    namePs: 'کارتن',
    nameEn: 'Carton / Box',
    category: 'count',
    hasStandardConversion: false,
  },
  // Time / Labour Units
  {
    id: 'day',
    symbol: 'day',
    nameFa: 'روزکار (روز)',
    namePs: 'ورځ',
    nameEn: 'Day / Shift',
    category: 'time',
    hasStandardConversion: false,
  },
  {
    id: 'hour',
    symbol: 'hr',
    nameFa: 'ساعتکار (ساعت)',
    namePs: 'ساعت',
    nameEn: 'Hour',
    category: 'time',
    hasStandardConversion: false,
  },
  {
    id: 'other',
    symbol: 'other',
    nameFa: 'واحد دیگر',
    namePs: 'بل واحد',
    nameEn: 'Other Custom Unit',
    category: 'count',
    hasStandardConversion: false,
  },
];

export interface ConversionResult {
  convertedQuantity: number;
  targetUnit: string;
  explanation: string;
  isStandard: boolean;
}

export function calculateUnitConversion(
  quantity: number,
  unitId: string,
  customCapacity?: {
    truckCapacity?: number;
    truckTargetUnit?: string;
    bagWeightKg?: number;
  }
): ConversionResult | null {
  if(!Number.isFinite(quantity)||quantity<=0)return null;
  if(customCapacity?.bagWeightKg!==undefined&&(!Number.isFinite(customCapacity.bagWeightKg)||customCapacity.bagWeightKg<=0))return null;
  if(customCapacity?.truckCapacity!==undefined&&(!Number.isFinite(customCapacity.truckCapacity)||customCapacity.truckCapacity<=0))return null;
  if (isNaN(quantity) || quantity <= 0) return null;

  // 1. Standard: Ton -> kg
  if (unitId === 'Ton') {
    const kg = quantity * 1000;
    return {
      convertedQuantity: kg,
      targetUnit: 'kg',
      explanation: `${quantity.toLocaleString()} Ton = ${kg.toLocaleString()} kg (1 Ton = 1,000 kg)`,
      isStandard: true,
    };
  }

  // 2. Standard: kg -> Ton
  if (unitId === 'kg') {
    const tons = quantity / 1000;
    return {
      convertedQuantity: tons,
      targetUnit: 'Ton',
      explanation: `${quantity.toLocaleString()} kg = ${tons.toLocaleString()} Ton`,
      isStandard: true,
    };
  }

  // 3. Standard: Seer -> kg
  if (unitId === 'seer') {
    const kg = quantity * 7;
    return {
      convertedQuantity: kg,
      targetUnit: 'kg',
      explanation: `${quantity.toLocaleString()} سیر = ${kg.toLocaleString()} kg (1 سیر = 7 kg)`,
      isStandard: true,
    };
  }

  // 4. Standard: Kharwar -> kg
  if (unitId === 'kharwar') {
    const kg = quantity * 560;
    return {
      convertedQuantity: kg,
      targetUnit: 'kg',
      explanation: `${quantity.toLocaleString()} خروار = ${kg.toLocaleString()} kg (1 خروار = 80 سیر = 560 kg)`,
      isStandard: true,
    };
  }

  // 5. Standard: Meter -> cm
  if (unitId === 'meter') {
    const cm = quantity * 100;
    return {
      convertedQuantity: cm,
      targetUnit: 'cm',
      explanation: `${quantity} m = ${cm} cm`,
      isStandard: true,
    };
  }

  // 6. Variable: Cement Bag (requires capacity, default 50kg)
  if (unitId === 'bag') {
    const bagKg = customCapacity?.bagWeightKg || 50;
    const totalKg = quantity * bagKg;
    const totalTons = totalKg / 1000;
    return {
      convertedQuantity: totalKg,
      targetUnit: 'kg',
      explanation: `${quantity.toLocaleString()} خریطه = ${totalKg.toLocaleString()} kg (${totalTons} Ton) با ظرفیت ${bagKg} کیلو`,
      isStandard: false,
    };
  }

  // 7. Variable: Truck (requires configured capacity, user rule)
  if (unitId === 'truck') {
    if (customCapacity?.truckCapacity && customCapacity.truckCapacity > 0) {
      const targetUnit = customCapacity.truckTargetUnit || 'Ton';
      const totalCapacity = quantity * customCapacity.truckCapacity;
      return {
        convertedQuantity: totalCapacity,
        targetUnit,
        explanation: `${quantity} موتر = ${totalCapacity.toLocaleString()} ${targetUnit} (ظرفیت هر موتر: ${customCapacity.truckCapacity} ${targetUnit})`,
        isStandard: false,
      };
    }
  }

  return null;
}
