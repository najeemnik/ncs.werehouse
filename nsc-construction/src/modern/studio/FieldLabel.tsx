import React from 'react';
/** Explicit names keep select options and textarea values out of the field label. */
export function FieldLabel({children,...props}:React.LabelHTMLAttributes<HTMLLabelElement>){
 const text=(node:React.ReactNode):string=>{if(typeof node==='string'||typeof node==='number')return String(node);if(!React.isValidElement(node))return '';if(['input','textarea','select','output','small'].includes(String(node.type)))return '';return React.Children.toArray((node.props as any).children).map(text).join('');};
 const name=React.Children.toArray(children).map(text).join('').trim();const fields=React.Children.map(children,child=>React.isValidElement(child)&&['input','textarea','select'].includes(String(child.type))?React.cloneElement(child as React.ReactElement<any>,{'aria-label':(child.props as any)['aria-label']||name}):child);
 return <label {...props}>{fields}</label>;
}
