/**
 * Arrêts regroupés sous un parent (docs/plan-arrets-parents.md) : règles d'éligibilité à la fusion côté admin
 * et enveloppe d'un groupe pour la carte. Module pur (aucune dépendance), testé par tests/js.
 */

/** Famille de modalité : TER et TGV (gares) ne forment qu'une famille, les autres sont distinctes. */
const famille = (t) => (t === "ter" || t === "tgv" ? "rail" : t);

/** Deux listes de modalités partagent-elles une famille ? (règle intra-mode de la fusion) */
export function memeMode(typesA, typesB) {
  const fa = new Set((typesA ?? []).map(famille));
  return (typesB ?? []).some((t) => fa.has(famille(t)));
}

/**
 * Deux arrêts peuvent-ils être fusionnés ? Un enfant ne l'est pas (détacher, ou fusionner son parent) ; un parent
 * et un orphelin, ou deux parents, oui. Reflète les refus de `api/merge_arrets.php`.
 * @returns {{ok: true} | {ok: false, raison: string}}
 */
export function fusionnable(a, b) {
  if (a.id === b.id) return { ok: false, raison: "même arrêt" };
  for (const x of [a, b]) {
    if (x.deleted) return { ok: false, raison: "arrêt supprimé" };
    if (x.parent_id != null) return { ok: false, raison: "arrêt enfant : détachez-le, ou fusionnez son parent" };
  }
  if (!memeMode(a.types, b.types)) return { ok: false, raison: "modes de transport différents" };
  return { ok: true };
}

const M_PAR_DEGRE_LAT = 111320;
const POINTS_PAR_CERCLE = 16;

/** Enveloppe convexe (chaîne monotone d'Andrew) de points [x, y] ; renvoie les sommets sans répétition. */
function enveloppeConvexe(points) {
  const pts = [...points].sort((p, q) => p[0] - q[0] || p[1] - q[1]);
  if (pts.length < 3) return pts;
  const cross = (o, a, b) => (a[0] - o[0]) * (b[1] - o[1]) - (a[1] - o[1]) * (b[0] - o[0]);
  const bas = [];
  for (const p of pts) {
    while (bas.length >= 2 && cross(bas[bas.length - 2], bas[bas.length - 1], p) <= 0) bas.pop();
    bas.push(p);
  }
  const haut = [];
  for (const p of [...pts].reverse()) {
    while (haut.length >= 2 && cross(haut[haut.length - 2], haut[haut.length - 1], p) <= 0) haut.pop();
    haut.push(p);
  }
  return [...bas.slice(0, -1), ...haut.slice(0, -1)];
}

/**
 * Enveloppe d'un groupe d'arrêts : enveloppe convexe des positions élargie de `rayonM` mètres (somme de Minkowski
 * avec un disque = enveloppe convexe des cercles posés sur chaque position). Anneau fermé [[lng, lat], …], ou
 * null s'il y a moins de deux positions distinctes (un parent à un seul enfant n'a pas d'enveloppe).
 */
export function enveloppe(positions, rayonM = 15) {
  const uniques = [...new Map(positions.map((p) => [`${p.lat},${p.lng}`, p])).values()];
  if (uniques.length < 2) return null;
  const lat0 = uniques.reduce((s, p) => s + p.lat, 0) / uniques.length;
  const mParDegLng = M_PAR_DEGRE_LAT * Math.cos((lat0 * Math.PI) / 180);
  const dLat = rayonM / M_PAR_DEGRE_LAT;
  const dLng = rayonM / mParDegLng;
  const cercles = [];
  for (const p of uniques) {
    for (let i = 0; i < POINTS_PAR_CERCLE; i++) {
      const t = (2 * Math.PI * i) / POINTS_PAR_CERCLE;
      cercles.push([p.lng + dLng * Math.cos(t), p.lat + dLat * Math.sin(t)]);
    }
  }
  const ring = enveloppeConvexe(cercles);
  return [...ring, ring[0]];
}
