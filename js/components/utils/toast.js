/**
 * Minimal vanilla toast helper (Daisy UI classes), mirroring the Vue
 * `useToast` composable for pages that are not Vue-mounted.
 *
 *   showToast("Message", "error");
 */
const CONTAINER_ID = "vg-toast-container";

// Classes explicites (et non `alert-${type}`) pour que Tailwind les détecte au build.
const TYPE_CLASS = {
  success: "alert-success",
  error: "alert-error",
  info: "alert-info",
};

function getContainer() {
  let el = document.getElementById(CONTAINER_ID);
  if (!el) {
    el = document.createElement("div");
    el.id = CONTAINER_ID;
    el.className = "toast toast-end z-[9999]";
    document.body.appendChild(el);
  }
  return el;
}

/**
 * @param {string} message
 * @param {"success"|"error"|"info"} [type="info"]
 * @param {number} [duration=5000] ms; 0 = sticky until closed
 */
export function showToast(message, type = "info", duration = 5000) {
  const container = getContainer();

  const toast = document.createElement("div");
  toast.className = `alert ${TYPE_CLASS[type] || TYPE_CLASS.info} shadow-lg`;
  toast.setAttribute("role", type === "error" ? "alert" : "status");

  const span = document.createElement("span");
  span.textContent = message;

  const close = document.createElement("button");
  close.type = "button";
  close.className = "btn btn-sm btn-ghost btn-circle";
  close.setAttribute("aria-label", "Fermer");
  close.textContent = "✕";

  const remove = () => toast.remove();
  close.addEventListener("click", remove);

  toast.append(span, close);
  container.appendChild(toast);

  if (duration > 0) setTimeout(remove, duration);
  return remove;
}
