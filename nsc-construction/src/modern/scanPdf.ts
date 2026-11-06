/** Single-image PDF; binary stream length and byte offsets are explicit. */
export function imagePdf(jpeg:Uint8Array,width:number,height:number):Blob{
 const encoder=new TextEncoder(),chunks:Uint8Array[]=[],offsets=[0];let length=0;
 const write=(part:string|Uint8Array)=>{const bytes=typeof part==='string'?encoder.encode(part):part;chunks.push(bytes);length+=bytes.length;};
 const object=(id:number,body:string)=>{offsets[id]=length;write(id+' 0 obj\n'+body+'\nendobj\n');};
 const landscape=width>height,pw=landscape?842:595,ph=landscape?595:842,scale=Math.min((pw-24)/width,(ph-24)/height),w=width*scale,h=height*scale;
 write('%PDF-1.4\n');object(1,'<< /Type /Catalog /Pages 2 0 R >>');object(2,'<< /Type /Pages /Count 1 /Kids [3 0 R] >>');object(3,`<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${pw} ${ph}] /Resources << /XObject << /Image 4 0 R >> >> /Contents 5 0 R >>`);
 offsets[4]=length;write(`4 0 obj\n<< /Type /XObject /Subtype /Image /Width ${width} /Height ${height} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ${jpeg.length} >>\nstream\n`);write(jpeg);write('\nendstream\nendobj\n');
 const content=`q ${w.toFixed(4)} 0 0 ${h.toFixed(4)} ${((pw-w)/2).toFixed(4)} ${((ph-h)/2).toFixed(4)} cm /Image Do Q\n`;object(5,`<< /Length ${encoder.encode(content).length} >>\nstream\n${content}endstream`);
 const start=length;write('xref\n0 6\n0000000000 65535 f \n');for(let i=1;i<=5;i++)write(String(offsets[i]).padStart(10,'0')+' 00000 n \n');write(`trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n${start}\n%%EOF\n`);
 return new Blob(chunks as BlobPart[],{type:'application/pdf'});
}
