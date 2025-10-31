(function(){
  'use strict';

  function ready(fn){
    if (document.readyState === 'loading'){
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  function mapFormat(format){
    var lower = String(format || '').toLowerCase();
    if (lower === 'hibc'){ return 'CODE39'; }
    return 'CODE128';
  }

  function renderBarcode(item){
    if (typeof JsBarcode === 'undefined'){ return; }
    var value = item.getAttribute('data-barcode-value');
    if (!value){ return; }

    var target = item.querySelector('.ba-barcode-canvas');
    if (!target){ return; }

    var barcodeFormat = mapFormat(item.getAttribute('data-barcode-format'));
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('focusable', 'false');
    svg.setAttribute('aria-hidden', 'true');

    target.innerHTML = '';
    target.appendChild(svg);

    try {
      JsBarcode(svg, value, {
        format: barcodeFormat,
        displayValue: false,
        height: 64,
        lineColor: '#111',
        margin: 0,
        textMargin: 0
      });
    } catch (err){
      target.innerHTML = '';
      target.appendChild(document.createTextNode(value));
      if (window.console && console.warn){
        console.warn('EventusAPI: no se pudo renderizar el codigo de barras', err);
      }
    }
  }

  ready(function(){
    var nodes = document.querySelectorAll('.ba-barcode-item[data-barcode-value]');
    if (!nodes.length){
      return;
    }

    if (typeof JsBarcode === 'undefined'){
      if (window.console && console.warn){
        console.warn('EventusAPI: JsBarcode no esta disponible.');
      }
      return;
    }

    nodes.forEach(renderBarcode);
  });
})();



