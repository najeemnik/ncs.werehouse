import assert from 'node:assert/strict';
import {csvCell} from '../src/modern/csv';
const dangerous=['=2+2','+SUM(1,2)','-SUM(1,2)','@SUM(1,2)','   =2+2','\t=2+2','\r=2+2','\n=2+2','\uFEFF=2+2','\u00A0=2+2'];
for(const value of dangerous)assert(csvCell(value).startsWith('"\''),'spreadsheet must receive a text marker');
assert.equal(csvCell('Ahmad "Nik"'),'"Ahmad ""Nik"""');assert.equal(csvCell('قرضدار'),'"قرضدار"');assert.equal(csvCell('12.50'),'"12.50"');assert.equal(csvCell('First\nsecond'),'"First\nsecond"');
console.log('14 CSV security checks passed: formulas with whitespace/newline/BOM prefixes are text; quotes, Dari, numbers and multiline content preserved.');
