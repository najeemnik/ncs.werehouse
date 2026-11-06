/** Decimal half-away-from-zero rounding, consistent with PHP round() for money. */
export function roundMoney(value:number):number{
 if(!Number.isFinite(value)||value===0)return value;
 const [coefficient,exponent='0']=Math.abs(value).toPrecision(15).split('e');
 const cents=Math.round(Number(coefficient+'e'+(Number(exponent)+2)));
 return cents===0?0:Math.sign(value)*cents/100;
}
