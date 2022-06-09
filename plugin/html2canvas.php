<?php
    define('WP_USE_THEMES', false);  // No need for the template engine
    require_once( '../../../../wp-load.php' );  // Load WordPress Core 
?>
<style>
body{position: relative;}
:root{--color-fa:#fafafa;--color-e:#eee;--color-d:#ddd;--color-c:#ccc;--color-9:#949494;--color-8:#888;--color-6:#666;--color-4a:#4a4a4a;--color-3a:#3a3a3a;--color-2b:#2b2b2b;--padding-num:15px;--radius:10px}
.captureBox{width:100%;height:100%;}#capture{max-width:300px;min-width:280px;color:var(--color-2b);text-align:center;border-radius:var(--radius);background:var(--color-fa);overflow:hidden;font-family:var(--font-ms);position:fixed;top:0;left:0;z-index:-99999;/*transform:scale(2);-webkit-transform:scale(2);*/}#capture header{width:auto;height:auto;margin:0 auto;padding:20px var(--padding-num) 0;background:var(--color-e);border-top-left-radius:var(--radius);border-top-right-radius:var(--radius)}#capture header img{max-width:100%;min-height:168px;max-height:188px;width:100%;object-fit:cover;border-radius:inherit;background:currentColor;margin:0 auto;display:inherit}#capture aside{text-align:left;padding:25px var(--padding-num) var(--padding-num);box-sizing:border-box;position:relative}#capture aside h3{margin:0;max-width:52%;text-overflow:ellipsis;overflow:hidden;/*max-height:50px*/}#capture aside p{color:var(--color-6);font-size:0.8rem;font-weight:300;line-height:23px;min-height:36px}#capture aside small{color:var(--color-c);width:100%;display:inherit;text-align:right;font-size:12px;padding-top:10px}#capture aside small span{margin:auto 5px}#capture aside #qrcode{width:100px;height:100px;background:var(--color-fa);padding:10px;box-sizing:inherit;position:absolute;top:-50px;right:30px;box-shadow:rgb(0 0 0 / 0.18) 0px 5px 20px 0px}#capture aside #qrcode img{width:100%;height:100%}#capture footer{color:var(--color-c);font-size:12px;padding:15px 0;border-top:1px solid var(--color-e)}#html2img::before{width:200%;height:2px}#html2img::after{width:2px;height:150%}#html2img{padding:var(--padding-num);/*border:2px solid red;*/box-sizing:border-box;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);-webkit-transform:translate(-50%,-50%);z-index:99999}#mask,#html2img::before,#html2img::after{content:'';/*background:red;*/position:absolute;top:inherit;left:inherit;transform:inherit;-webkit-transform:inherit;z-index:-1}#mask{width:100%;height:100%;background: rgb(0 0 0 / 36%);top:0;left:0;z-index: 9999}#html2canvas{max-width:100%;max-height:100%;/*transform:translate(0,-0.5px)*/}#html2canvas img#loading{height:auto;position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);-webkit-transform:translate(-50%,-50%)}#html2canvas img{max-width:100%;border-radius:inherit;display:inherit}#html2canvas #loadbox{min-width:188px;min-height:388px;max-height:404px;background:var(--color-fa);box-shadow:rgb(0 0 0 / 0.18) 0px 5px 20px 0px;border-radius: var(--radius);position:relative}#loadbox h3{color:var(--color-fa);font-size: 1rem;line-height:66px;margin:0 auto;width:100%;background:var(--color-3a);background:linear-gradient(to right, var(--color-2b), var(--color-3a));border-top-left-radius:var(--radius);border-top-right-radius:var(--radius);position:absolute;top:0}#loadbox img#loading{top:58%}#loadbox #cancel:hover{transform:rotate(-90deg);-webkit-transform:rotate(90deg)}#loadbox #cancel{position: absolute;top: -22px;right: -22px;z-index: 1;background: var(--color-3a);border: 4px solid var(--color-e);border-radius: 50%;padding: 20px;cursor:pointer;transition:transform .35s ease}span#cancel:before{transform:translate(-50%,-50%) rotate(45deg)}span#cancel::after{transform:translate(-50%,-50%) rotate(-45deg)}span#cancel:before,span#cancel:after{content:"";width:58%;height:2px;background:var(--color-fa);position:inherit;top:50%;left:50%}#loadbox #poster{border-radius:inherit;display:block;position:inherit}
#capture header em{display: block;width: 100%;min-height:168px;max-height:188px;border-radius:inherit;}
</style>
<script>
	var dynamicLoad = function(jsUrl,fn){
		var _doc = document.getElementsByTagName('head')[0],
			script = document.createElement('script');
			script.setAttribute('type','text/javascript');
			script.setAttribute('async',true);
			script.setAttribute('src',jsUrl);
			_doc.appendChild(script);
		script.onload = script.onreadystatechange = function(){
			if(!this.readyState || this.readyState=='loaded' || this.readyState=='complete'){
				fn();
			}
			script.onload = script.onreadystatechange = null;
		}
	};
	function qrcodes(){
		let url = location.href;
		var qrcode = new QRCode(document.getElementById("qrcode"), {
			text: url,
			width: 100,
			height: 100,
			colorDark : "#000000",
			colorLight : "#ffffff",
			correctLevel : QRCode.CorrectLevel.L
		});
	};
	function h2c(){
		html2canvas(
		  $("#capture")[0],{
		    useCORS: true,
		    allowTaint: true,
		    scrollX: 0,
		    scrollY: 0,
		    backgroundColor: null
	    }).then(canvas => {
			let baseUrl = canvas.toDataURL("image/png"),
				newImg = document.createElement("img"),
				imgDom = '<img src="'+baseUrl+'" />';
			newImg.src = baseUrl;
			document.getElementById('poster').innerHTML+=imgDom
		})
	};
	dynamicLoad('<?php custom_cdn_src(); ?>/js/qrcode/qrcode.min.js',function(){
		qrcodes();
		dynamicLoad('<?php custom_cdn_src(); ?>/js/html2canvas/html2canvas.min.js',function(){
			h2c();
		});
	})
</script>