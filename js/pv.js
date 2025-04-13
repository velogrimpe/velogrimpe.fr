/** PAGEVIEW TRACKER - RGPD COMPLIANT - NO PERSONAL DATA RECORDED */

function main() {
  // Check if the user ID already exists in localStorage
  // If no ID exists, create a new one using native crypto API and store it
  const userIdKey = "user_id";
  let userId = localStorage.getItem(userIdKey);
  if (!userId) {
    userId = crypto.randomUUID(); // Generates a unique, random UUID
  }
  localStorage.setItem(userIdKey, userId);

  const url = window.location.hostname.replace(/^www\./, "");
  if (url.includes("localhost")) {
    return;
  }

  fetch("https://couble.eu/api/event", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      d: url,
      e: "pageviews",
      p: window.location.pathname + window.location.search,
      u: userId,
    }),
  });
}

main();
