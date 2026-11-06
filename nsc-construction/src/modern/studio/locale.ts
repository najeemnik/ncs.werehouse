/** Localized operation errors; field keys and formula syntax remain stable. */
export function studioError(value:any,language:string):string{
 const message=typeof value==='string'?value:String(value?.message||value);if(language==='en'||/[\u0600-\u06ff]/.test(message))return message;
 const rules:[RegExp,string,string][]=[
 [/another.*session|changed.*session|revision|another session/i,'نشست دیگر تغییر آورده است. تغییرات خود را دانلود کنید و نسخهٔ سرور را بازخوانی نمایید.','بلې ناستې بدلون راوستی. خپل بدلونونه ښکته او د سرور نسخه بیا پرانیزئ.'],
 [/division by zero/i,'تقسیم بر صفر ممکن نیست. مقدار فیلد را بررسی کنید.','پر صفر وېش امکان نه لري. د فیلډ ارزښت وګورئ.'],
 [/circular|cycle/i,'فورمول‌ها به یکدیگر برگشته‌اند؛ وابستگی دوری را اصلاح کنید.','فورمولونه دوراني تړاو لري؛ تړاو سم کړئ.'],
 [/unknown.*field|missing numeric field/i,'کود فیلد عددی در فورمول درست نیست یا موجود نیست.','په فورمول کې د عددي فیلډ کوډ ناسم دی یا نشته.'],
 [/required:/i,'فیلد ضروری را درج کنید.','اړین فیلډ ډک کړئ.'],
 [/unknown function|unknown operation/i,'از تابع‌ها و عملگرهای مجاز فورمول استفاده کنید.','د فورمول له مجازو تابعو او عملګرو کار واخلئ.'],
 [/precision|decimal/i,'دقت اعشاری باید از صفر تا شش باشد.','اعشاري دقت باید له صفر تر شپږ وي.'],
 [/exceed|too (complex|deep)|maximum|too many/i,'اندازه یا مقدار از حد مجاز بیشتر است.','اندازه یا ارزښت له مجاز حد څخه لوړ دی.'],
 [/formula|argument|token|bracket/i,'ساختار فورمول درست نیست. قوس‌ها و مقدارها را بررسی کنید.','د فورمول جوړښت ناسم دی. قوسونه او ارزښتونه وګورئ.'],
 [/final record|final values|locked/i,'ثبت نهایی یا خدمت قفل‌شده قابل تغییر نیست.','وروستی ثبت یا تړل شوی خدمت د بدلون وړ نه دی.'],
 [/project.*valid|select.*project|project.*found/i,'اول یک پروژهٔ معتبر انتخاب کنید.','لومړی یوه معتبره پروژه وټاکئ.'],
 [/authorized|access|required.*office|permission|disabled/i,'برای این گزینه یا پروژه اجازه ندارید.','دې چارې یا پروژې ته اجازه نه لرئ.'],
 [/https|url/i,'یک لینک معتبر HTTPS درج کنید.','یو معتبر HTTPS تړونی ولیکئ.'],
 [/invalid number|invalid choice|invalid text|invalid date/i,'مقدار فیلد درست نیست. عدد، تاریخ یا گزینه را بررسی کنید.','د فیلډ ارزښت ناسم دی. عدد، نېټه یا غوراوی وګورئ.'],
 [/configuration|hierarchy|import|json/i,'ساختار تنظیمات درست نیست. فایل و مشخصات عناصر را بررسی کنید.','د تنظیماتو جوړښت ناسم دی. فایل او د عناصرو ځانګړنې وګورئ.'],
 ];for(const[pattern,fa,ps]of rules)if(pattern.test(message))return language==='ps'?ps:fa;return language==='ps'?'چاره بشپړه نه شوه؛ معلومات وګورئ او بیا هڅه وکړئ.':'عملیات تکمیل نشد؛ معلومات را بررسی و دوباره تلاش کنید.';
}
