jQuery(document).ready(function ($) {
  if (typeof optipubGTM !== 'undefined' && optipubGTM.containers && optipubGTM.containers.length > 0) {
    optipubGTM.containers.forEach(function (containerId) {
      initializeGTMContainer(containerId);
    });
  }
});

function initializeGTMContainer(containerId) {
  if (!containerId) {
    return;
  }

  if (window.dataLayer && window.dataLayer.find && window.dataLayer.find(item => item['gtm.container'] === containerId)) {
    return;
  }

  window.dataLayer = window.dataLayer || [];

  window.dataLayer.push({
    'gtm.start': new Date().getTime(),
    'gtm.container': containerId,
    event: 'gtm.js'
  });

  var script = document.createElement('script');
  script.async = true;
  script.src = 'https://www.googletagmanager.com/gtm.js?id=' + containerId;

  var firstScript = document.getElementsByTagName('script')[0];
  firstScript.parentNode.insertBefore(script, firstScript);

  console.log('GTM container initialized:', containerId);
}
