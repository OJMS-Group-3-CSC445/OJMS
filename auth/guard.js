(function () {
  const role = document.currentScript?.dataset.role || "author";
  window.OJMS_AUTHORIZED = Boolean(
    window.OJMSApi && window.OJMSApi.requireRole(role)
  );
  if (!window.OJMS_AUTHORIZED) {
    document.documentElement.hidden = true;
  }
})();
