/**
 * UI de soumission de formulaire : à l'envoi, désactive le(s) bouton(s) submit
 * et affiche un spinner « Envoi en cours… ». S'applique automatiquement à tous
 * les formulaires de la page.
 *
 * - Formulaires classiques (navigation) : géré automatiquement.
 * - Formulaires AJAX (handler qui fait preventDefault) : le handler dédié pilote
 *   le spinner via window.formSubmitUI.setSubmitting() / resetSubmitting().
 * - Formulaires <form method="dialog"> (modales DaisyUI) et ceux marqués
 *   data-no-submit-ui : ignorés.
 */
(function () {
  const SPINNER = '<span class="loading loading-spinner loading-xs"></span> ';
  const LABEL = 'Envoi en cours…';

  function submitButtons(form) {
    const selector = 'button[type="submit"], button:not([type]), input[type="submit"]';
    const buttons = new Set(form.querySelectorAll(selector));
    if (form.id) {
      document
        .querySelectorAll(`[type="submit"][form="${CSS.escape(form.id)}"]`)
        .forEach((b) => buttons.add(b));
    }
    return buttons;
  }

  function setSubmitting(form) {
    submitButtons(form).forEach((btn) => {
      if (btn.dataset.vgSubmitting === '1') return;
      btn.dataset.vgSubmitting = '1';
      btn.dataset.vgOriginalHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = SPINNER + LABEL;
    });
  }

  function resetSubmitting(form) {
    submitButtons(form).forEach((btn) => {
      if (btn.dataset.vgSubmitting !== '1') return;
      btn.disabled = false;
      if (btn.dataset.vgOriginalHtml !== undefined) btn.innerHTML = btn.dataset.vgOriginalHtml;
      delete btn.dataset.vgSubmitting;
      delete btn.dataset.vgOriginalHtml;
    });
  }

  function onSubmit(e) {
    const form = e.currentTarget;
    if ((form.getAttribute('method') || '').toLowerCase() === 'dialog') return;
    if (form.hasAttribute('data-no-submit-ui')) return;
    // On attend un tick : si un handler AJAX a appelé preventDefault, c'est lui
    // qui gère le spinner. Sinon la page va naviguer, on affiche le spinner.
    queueMicrotask(() => {
      if (!e.defaultPrevented) setSubmitting(form);
    });
  }

  function attach(form) {
    if (form.dataset.vgSubmitUi === '1') return;
    form.dataset.vgSubmitUi = '1';
    form.addEventListener('submit', onSubmit);
  }

  function attachAll() {
    document.querySelectorAll('form').forEach(attach);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', attachAll);
  } else {
    attachAll();
  }

  // Restaure les boutons si la page revient du cache (bouton précédent/suivant),
  // sinon un spinner figé pourrait rester affiché.
  window.addEventListener('pageshow', () => {
    document.querySelectorAll('form').forEach(resetSubmitting);
  });

  window.formSubmitUI = { setSubmitting, resetSubmitting, attach };
})();
