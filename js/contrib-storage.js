/**
 * Module de stockage des informations contributeur
 * Utilise localStorage pour persister nom/email entre les sessions
 */

const STORAGE_KEY_NOM = 'velogrimpe_contrib_nom';
const STORAGE_KEY_EMAIL = 'velogrimpe_contrib_email';

/**
 * Recupere les infos contributeur du localStorage
 * @returns {{nom: string, email: string}}
 */
function getContribInfo() {
  return {
    nom: localStorage.getItem(STORAGE_KEY_NOM) || '',
    email: localStorage.getItem(STORAGE_KEY_EMAIL) || ''
  };
}

/**
 * Sauvegarde les infos contributeur dans localStorage
 * @param {string} nom
 * @param {string} email
 */
function saveContribInfo(nom, email) {
  if (nom) {
    localStorage.setItem(STORAGE_KEY_NOM, nom);
  }
  if (email) {
    localStorage.setItem(STORAGE_KEY_EMAIL, email);
  }
}

/**
 * Pre-remplit les champs nom_prenom et email du formulaire
 * avec les valeurs du localStorage (si non deja remplis)
 */
function prefillContribInputs() {
  const { nom, email } = getContribInfo();

  const nomInput = document.getElementById('nom_prenom');
  if (nomInput && !nomInput.value && nom) {
    nomInput.value = nom;
  }

  const emailInput = document.getElementById('email');
  if (emailInput && !emailInput.value && email) {
    emailInput.value = email;
  }
}

/**
 * Attache un listener au formulaire pour sauvegarder les infos a la soumission
 * @param {HTMLFormElement} form
 */
function attachFormSaveListener(form) {
  if (!form) return;

  form.addEventListener('submit', () => {
    const nomInput = document.getElementById('nom_prenom');
    const emailInput = document.getElementById('email');

    if (nomInput?.value) {
      saveContribInfo(nomInput.value, emailInput?.value || '');
    }
  });
}

// Expose as global for non-module usage
if (typeof window !== 'undefined') {
  window.contribStorage = {
    getContribInfo,
    saveContribInfo,
    prefillContribInputs,
    attachFormSaveListener
  };
}
