/* No network access: provisioning data stays in the authenticated page. */
(() => {
 'use strict';const canvas=document.getElementById('otp-qr');if(!canvas||!window.qrcodegen)return;
 const qr=qrcodegen.QrCode.encodeText(canvas.dataset.uri,qrcodegen.QrCode.Ecc.MEDIUM),border=4,scale=6,size=qr.size+border*2;
 canvas.width=canvas.height=size*scale;const c=canvas.getContext('2d');c.fillStyle='#fff';c.fillRect(0,0,canvas.width,canvas.height);c.fillStyle='#000';
 for(let y=0;y<qr.size;y++)for(let x=0;x<qr.size;x++)if(qr.getModule(x,y))c.fillRect((x+border)*scale,(y+border)*scale,scale,scale);
})();
