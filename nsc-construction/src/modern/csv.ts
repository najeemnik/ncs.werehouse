/** Preserve CSV text while preventing spreadsheet formulas, including whitespace prefixes. */
export function csvCell(value:string):string{
 const safe=/^[\s\uFEFF]*[=+\-@]/u.test(value)||/^[\t\r\n]/.test(value)?"'"+value:value;
 return '"'+safe.replaceAll('"','""')+'"';
}
