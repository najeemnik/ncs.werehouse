import {CHART_OF_ACCOUNTS} from '../src/data/chartOfAccounts';
import {writeFileSync} from 'node:fs';
import {modules} from '../src/modern/model';
import {labels} from '../src/modern/i18n';
writeFileSync('api/ui-catalog.json',JSON.stringify({accounts:CHART_OF_ACCOUNTS.map(({code,parentCode})=>({code,parentCode})),labels:Object.keys(labels),modules:modules.map(m=>({id:m.id,collection:({steelRecords:'steel',concreteRecords:'concrete',laborRecords:'labor'} as any)[m.collection]||m.collection,fields:m.fields.map(f=>({key:f.key,type:f.type,required:!!f.required})),columns:[...new Set([...m.columns,...m.fields.map(f=>f.key),'approvalStatus','effectiveAmount','creditedAmount','adjustedTotal'])]}))},null,2)+'\n');
