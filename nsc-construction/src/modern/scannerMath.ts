export type ScanPoint={x:number;y:number};
export type ScanQuad=[ScanPoint,ScanPoint,ScanPoint,ScanPoint];
export const initialQuad=():ScanQuad=>[{x:.05,y:.05},{x:.95,y:.05},{x:.95,y:.95},{x:.05,y:.95}];
export function validQuad(points:ScanQuad):boolean{
 if(points.length!==4||points.some(p=>!Number.isFinite(p.x)||!Number.isFinite(p.y)||p.x<0||p.y<0||p.x>1||p.y>1))return false;
 const cross=points.map((p,i)=>{const b=points[(i+1)%4],c=points[(i+2)%4];return(b.x-p.x)*(c.y-b.y)-(b.y-p.y)*(c.x-b.x);});
 return cross.every(v=>v>1e-5)&&Math.abs(points.reduce((s,p,i)=>s+p.x*points[(i+1)%4].y-p.y*points[(i+1)%4].x,0))>.002;
}
/** Unit square to a convex quadrilateral; output is sampled back into the source. */
export function perspectiveMatrix(p:ScanQuad){
 const [a,b,c,d]=p,dx1=b.x-c.x,dx2=d.x-c.x,dx3=a.x-b.x+c.x-d.x,dy1=b.y-c.y,dy2=d.y-c.y,dy3=a.y-b.y+c.y-d.y;
 let g=0,h=0;
 if(Math.abs(dx3)+Math.abs(dy3)>1e-9){const determinant=dx1*dy2-dx2*dy1;if(Math.abs(determinant)<1e-12)throw Error('Invalid corners');g=(dx3*dy2-dx2*dy3)/determinant;h=(dx1*dy3-dx3*dy1)/determinant;}
 return [b.x-a.x+g*b.x,d.x-a.x+h*d.x,a.x,b.y-a.y+g*b.y,d.y-a.y+h*d.y,a.y,g,h] as const;
}
export function perspectivePoint(matrix:ReturnType<typeof perspectiveMatrix>,u:number,v:number){const [a,b,c,d,e,f,g,h]=matrix,k=g*u+h*v+1;return{x:(a*u+b*v+c)/k,y:(d*u+e*v+f)/k};}
export function scanSize(points:ScanQuad,width:number,height:number,max=2000){
 const distance=(a:ScanPoint,b:ScanPoint)=>Math.hypot((a.x-b.x)*width,(a.y-b.y)*height);
 const w=(distance(points[0],points[1])+distance(points[3],points[2]))/2,h=(distance(points[0],points[3])+distance(points[1],points[2]))/2,scale=Math.min(1,max/Math.max(w,h));
 return {width:Math.max(32,Math.round(w*scale)),height:Math.max(32,Math.round(h*scale))};
}
export function imageDimensions(bytes:ArrayBuffer,type:string):{width:number;height:number}|null{
 const b=new Uint8Array(bytes),v=new DataView(bytes);
 if(type==='image/png'&&b.length>=24&&v.getUint32(0)===0x89504e47)return{width:v.getUint32(16),height:v.getUint32(20)};
 if(type==='image/jpeg'&&b[0]===255&&b[1]===216){let i=2,orientation=1,result:{width:number;height:number}|null=null;while(i+4<b.length){if(b[i]!==255){i++;continue;}const marker=b[i+1];if(marker===218||marker===217)break;if(marker===0||marker===255){i++;continue;}const length=v.getUint16(i+2),end=i+2+length;if(length<2||end>b.length)return null;
  if(marker===225&&length>=16&&v.getUint32(i+4)===0x45786966&&v.getUint16(i+8)===0){const base=i+10,little=v.getUint16(base)===0x4949;const u16=(offset:number)=>v.getUint16(base+offset,little),u32=(offset:number)=>v.getUint32(base+offset,little);if(base+8<=end&&u16(2)===42){const directory=u32(4);if(base+directory+2<=end){const count=Math.min(u16(directory),256);for(let n=0;n<count;n++){const offset=directory+2+n*12;if(base+offset+12>end)break;if(u16(offset)===274&&u16(offset+2)===3&&u32(offset+4)===1){orientation=u16(offset+8);break;}}}}}
  if([192,193,194,195,197,198,199,201,202,203,205,206,207].includes(marker)){if(length<8)return null;result={height:v.getUint16(i+5),width:v.getUint16(i+7)};}i=end;
 }return result&&orientation>=5&&orientation<=8?{width:result.height,height:result.width}:result;}
 if(type==='image/webp'&&b.length>=30&&v.getUint32(0)===0x52494646&&v.getUint32(8)===0x57454250){const tag=v.getUint32(12);if(tag===0x56503858)return{width:1+b[24]+(b[25]<<8)+(b[26]<<16),height:1+b[27]+(b[28]<<8)+(b[29]<<16)};if(tag===0x5650384c&&b[20]===47){const bits=v.getUint32(21,true);return{width:(bits&16383)+1,height:((bits>>>14)&16383)+1};}if(tag===0x56503820)return{width:v.getUint16(26,true)&16383,height:v.getUint16(28,true)&16383};}
 return null;
}
