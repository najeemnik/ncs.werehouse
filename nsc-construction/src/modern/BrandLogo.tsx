import React from 'react';
/** The NSC identity is separate from a project's optional company logo. */
export function BrandLogo({className='',motion=true}:{className?:string;motion?:boolean}){
 return <img className={'nsc-brand-logo '+(motion?'nsc-brand-motion ':'')+className} src="/assets/logo.png" alt="Nik Smart Count" width={256} height={256} decoding="async" draggable={false}/>;
}
/** Creator identity belongs in the author introduction, never a user's avatar. */
export function CreatorLogo(){
 return <img className="nsc-creator-logo" src="/assets/creator-logo.png" alt="NiK — Ahmad Najeem Nik" width={128} height={156} decoding="async" draggable={false}/>;
}
