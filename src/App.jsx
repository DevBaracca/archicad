import React, { useEffect, useMemo, useState } from "react";

/* =================================================================
   Catalogues par défaut — repli si l'API du site est indisponible.
   En production, les catalogues sont gérés depuis le back-office
   (admin/configurateur.php) et servis par /php/api_catalogue.php :
   menuiseries (avec visuel), modèles de modules et enveloppe
   (murs, planchers, façades) — chaque élément porte un tarif.
================================================================= */
const MURS = [
  { id: "clt90", nom: "Mur CLT 90 mm", epaisseur: 0.09, materiau: "CLT", surface: "bardage_bois", rgb: [140, 107, 89], favori: "" },
  { id: "clt140", nom: "Mur CLT 140 mm", epaisseur: 0.14, materiau: "CLT", surface: "bardage_bois", rgb: [140, 107, 89], favori: "" },
  { id: "ossature160", nom: "Ossature bois 160 mm", epaisseur: 0.16, materiau: "Ossature bois", surface: "enduit", rgb: [194, 169, 133], favori: "" },
  { id: "bloc200", nom: "Bloc béton 200 mm", epaisseur: 0.20, materiau: "Béton", surface: "brique", rgb: [158, 158, 158], favori: "" },
];
const TOITURES = [
  { id: "monopente", nom: "Monopente", type: "monopente", epaisseur: 0.30, surface: "", rgb: [120, 113, 108], favori: "Baracca — Toiture monopente" },
  { id: "deuxpentes", nom: "Deux pentes", type: "deuxpentes", epaisseur: 0.30, surface: "", rgb: [120, 113, 108], favori: "Baracca — Toiture deux pentes" },
  { id: "terrasse", nom: "Terrasse plate", type: "terrasse", epaisseur: 0.25, surface: "", rgb: [120, 113, 108], favori: "Baracca — Toiture terrasse" },
];
const PLANCHERS = [
  { id: "clt_dalle", nom: "Dalle CLT + chape", epaisseur: 0.30, favori: "" },
  { id: "plancher_bois", nom: "Plancher bois sur lambourdes", epaisseur: 0.24, favori: "" },
  { id: "dalle_beton", nom: "Dalle béton", epaisseur: 0.20, favori: "" },
];
const FACADES = [
  { id: "bardage_bois", nom: "Bardage bois", image: "" },
  { id: "enduit", nom: "Enduit minéral", image: "" },
  { id: "tole", nom: "Tôle nervurée", image: "" },
  { id: "brique", nom: "Brique de parement", image: "" },
];
const FACES = [
  { key: "nord", label: "Mur Nord" },
  { key: "est", label: "Mur Est" },
  { key: "sud", label: "Mur Sud" },
  { key: "ouest", label: "Mur Ouest" },
];

const DEFAULTS = {
  nom: "Baracca_Module_01",
  moduleId: null, // modèle de module choisi dans le catalogue (null = sur mesure)
  longueur: 6.0,
  largeur: 3.0,
  hauteur: 2.8,
  murType: "clt140",
  toiture: "monopente",
  pente: 15,
  pignon: true,
  plancher: "clt_dalle",
  facade: "bardage_bois",
  surfaceToiture: null, // surface de toiture choisie (null = celle de la rubrique toitures)
  murs: {
    nord: { actif: true, ouvertures: [], surface: null }, // surface du mur (null = celle du type de mur)
    est: { actif: true, ouvertures: [], surface: null },
    sud: { actif: true, ouvertures: [], surface: null },
    ouest: { actif: true, ouvertures: [], surface: null },
  },
};

/* Endpoints du site (même origine : l'application est servie par le site PHP
   sous /configurateur/ et intégrée par iframe dans configurateur.php) */
const API_SESSION = "/php/api_session.php";
const API_CONFIGS = "/php/api_configurations.php";
const API_SOUMETTRE = "/php/soumettre.php";
const API_CATALOGUE = "/php/api_catalogue.php";

/* =================================================================
   Calculs
================================================================= */
/* Surface plancher : calculée sur la ligne directrice des murs (longueur ×
   largeur intérieures), hors dépassement du plancher */
const surfacePlancher = (d) => d.longueur * d.largeur;

/* Emprise au sol : surface plancher (ligne directrice) + épaisseur des murs,
   rejetée vers l'extérieur de la ligne directrice */
const empriseAuSol = (d, murDef) =>
  (d.longueur + 2 * murDef.epaisseur) * (d.largeur + 2 * murDef.epaisseur);

/* Épaisseur du plancher retenu (m), défaut 20 cm */
const epaisseurPlancher = (d, planchers) =>
  +((planchers.find((p) => p.id === d.plancher) || planchers[0])?.epaisseur ?? 0.20);

/* Élément de la rubrique « Toitures » référencé par le module (repli sur la
   recherche par type géométrique pour les anciennes configurations) */
const toitureDefOf = (d, env) =>
  env.toitures.find((t) => t.id === d.toiture)
  || env.toitures.find((t) => t.type === d.toiture)
  || env.toitures[0]
  || { id: "monopente", nom: "Monopente", type: "monopente", favori: "", epaisseur: 0.30, rgb: [120, 113, 108], surface: "" };
const toitTypeOf = (d, env) => toitureDefOf(d, env).type || "monopente";

function surfaceToiture(d, tt) {
  if (tt === "terrasse") return surfacePlancher(d);
  const angle = (d.pente * Math.PI) / 180;
  const base = tt === "monopente" ? d.largeur : d.largeur / 2;
  return d.longueur * (base / Math.cos(angle));
}

function hauteurFaitage(d, tt) {
  if (tt === "terrasse") return 0;
  const angle = (d.pente * Math.PI) / 180;
  const base = tt === "monopente" ? d.largeur : d.largeur / 2;
  return Math.tan(angle) * base;
}

const murLongueur = (d, face) =>
  face === "nord" || face === "sud" ? d.longueur : d.largeur;

/* Position des ouvertures sur le mur, mesurée depuis l'origine de la ligne
   directrice (à l'intérieur du mur, l'épaisseur étant rejetée vers
   l'extérieur) : affichée en cm, ajustée par pas de 60 cm (flèches),
   minimum 7,8 cm — pas de saisie libre (stockage en m) */
const POSITION_MIN = 0.078; // 7,8 cm
const POSITION_STEP = 0.6;  // 60 cm

/* Plancher : dépasse de 21,7 cm de chaque côté de la ligne directrice des
   murs (référence retenue dans l'export JSON) ; en élévation, la ligne
   directrice du plancher est en haut (point bas des murs) et le trait du
   sol est à 20 cm sous le bas du plancher */
const PLANCHER_DEPASSEMENT = 0.217; // 21,7 cm de chaque côté
const SOL_SOUS_PLANCHER = 0.20;     // 20 cm

const surfaceMenuiserie = (m) =>
  m.ouvertures.reduce((s, o) => s + o.largeur * o.hauteur, 0);

function validerOuverture(d, faceKey, o) {
  const L = murLongueur(d, faceKey);
  return o.position >= POSITION_MIN && o.position + o.largeur <= L && o.hauteur <= d.hauteur;
}

/* =================================================================
   Tarifs (issus des catalogues du back-office — champ « tarif » de
   chaque élément : modèle de module, mur, plancher, façade, menuiserie)
================================================================= */
const fmtEur = (n) =>
  (Math.round((+n || 0) * 100) / 100).toLocaleString("fr-FR", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }) + " €";

/* m -> cm, au dixième près (précision mm), virgule française (7,8 / 33,5 / 150) */
const fmtCm = (m) => String(Math.round(m * 1000) / 10).replace(".", ",");

/* m² -> « 42,0 m² » (virgule française, 1 décimale) */
const fmtM2 = (m2) => m2.toLocaleString("fr-FR", { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + " m²";

/* Couleur d'affichage des murs en hexadécimal (#rrggbb), à partir du rgb
   stocké dans le catalogue (le choix des couleurs reste en rgb côté back-office) */
const rgbHex = (rgb) =>
  "#" + (Array.isArray(rgb) ? rgb : [158, 158, 158])
    .map((v) => Math.max(0, Math.min(255, Math.round(+v || 0))).toString(16).padStart(2, "0"))
    .join("");

function tarifOuverture(o, menuiseries) {
  if (!menuiseries) return 0;
  const m = menuiseries.find((x) => x.id === o.menuiserieId);
  return m ? +m.tarif || 0 : 0;
}

/* coût au m² d'un élément de catalogue (murs, planchers, toitures, surfaces) */
const eurM2 = (item) => (item ? +item.tarif || 0 : 0);

/* Murs retenus pour le chiffrage (extérieurs, épaisseurs comprises), avec détail
   par mur : longueur extérieure (ligne directrice + 2 épaisseurs) × hauteur, moins
   les surfaces des ouvrants ; si pignon, + triangle de toiture sur est/ouest et
   épaisseur de toiture sur les 2 versants. */
function mursChiffrage(d, env) {
  const murDef = env.murs.find((m) => m.id === d.murType) || env.murs[0];
  const toiture = toitureDefOf(d, env);
  const toitType = toitTypeOf(d, env);
  const epMur = +(murDef?.epaisseur ?? 0.14);
  const epToit = +(toiture?.epaisseur ?? 0.30);
  const hF = hauteurFaitage(d, toitType);
  const cosPente = Math.cos((d.pente * Math.PI) / 180);
  let total = 0;
  const detail = [];
  FACES.forEach((f) => {
    if (!d.murs[f.key].actif) return;
    const longueurExt = murLongueur(d, f.key) + 2 * epMur; // épaisseurs aux 2 extrémités
    let area = longueurExt * d.hauteur;
    if (toitType === "deuxpentes" && d.pignon && (f.key === "est" || f.key === "ouest")) {
      const base = d.largeur + 2 * epMur;
      const triangle = (base * hF) / 2;                 // triangle du pignon
      const versant = base / 2 / cosPente;              // longueur d'un versant
      area += triangle + 2 * versant * epToit;          // + épaisseur de toiture sur les 2 versants
    }
    // les ouvrants remplacent de la surface de mur
    area -= d.murs[f.key].ouvertures.reduce((ss, o) => ss + o.largeur * o.hauteur, 0);
    const surfaceId = d.murs[f.key].surface ?? murDef?.surface ?? "";
    const sd = env.facades.find((f) => f.id === surfaceId);
    const amount = (eurM2(murDef) + eurM2(sd)) * area; // (coût mur + coût surface) × surface du mur
    total += area;
    detail.push({ key: f.key, label: f.label, surfaceId, area, amount });
  });
  return { total, detail };
}

/* Surfaces retenues pour le chiffrage : murs (avec détail par mur), plancher
   (ligne directrice), toiture (versant + 2 épaisseurs de toiture, haut et bas),
   ouvrants (nombre d'ouvertures, murs actifs). */
function surfacesChiffrage(d, env) {
  const toiture = toitureDefOf(d, env);
  const toitType = toitTypeOf(d, env);
  const epToit = +(toiture?.epaisseur ?? 0.30);
  const cosPente = Math.cos((d.pente * Math.PI) / 180);
  const M = mursChiffrage(d, env);
  const plancher = surfacePlancher(d);
  const baseToit = toitType === "monopente" ? d.largeur : d.largeur / 2;
  const toitureS = toitType === "terrasse"
    ? plancher
    : d.longueur * (baseToit / cosPente + 2 * epToit); // versant + ép. en haut et en bas
  const ouvrants = FACES.reduce(
    (n, f) => n + (d.murs[f.key].actif ? d.murs[f.key].ouvertures.length : 0),
    0
  );
  return { murs: M.total, mursDetail: M.detail, plancher, toiture: toitureS, ouvrants };
}

function tarifTotal(d, env) {
  const module = env.modules?.find((m) => m.id === d.moduleId);
  const mur = env.murs.find((m) => m.id === d.murType);
  const plancher = env.planchers.find((p) => p.id === d.plancher);
  const facade = env.facades.find((f) => f.id === d.facade);
  const toiture = toitureDefOf(d, env);
  const S = surfacesChiffrage(d, env);
  // Ouvrants : coût à l'unité (une unité par ouverture, murs actifs seulement)
  const menuiseries = FACES.reduce(
    (s, f) => s + (d.murs[f.key].actif
      ? d.murs[f.key].ouvertures.reduce((ss, o) => ss + tarifOuverture(o, env.menuiseries), 0)
      : 0),
    0
  );
  // murs : somme des montants par mur (coût mur + coût de la surface choisie × surface)
  const murAmount = S.mursDetail.reduce((s, m) => s + m.amount, 0);
  // toiture : (coût de la toiture + coût de la surface choisie) × surface toiture
  const sdToit = env.facades.find((f) => f.id === (d.surfaceToiture ?? toiture?.surface ?? ""));
  const toitureAmount = (eurM2(toiture) + eurM2(sdToit)) * S.toiture;
  const t = {
    module: module ? +module.tarif || 0 : 0,
    mur: murAmount,
    plancher: eurM2(plancher) * S.plancher,
    facade: facade ? +facade.tarif || 0 : 0,
    toiture: toitureAmount,
    menuiseries,
  };
  t.total = t.module + t.mur + t.plancher + t.facade + t.toiture + t.menuiseries;
  return t;
}

/* =================================================================
   Export JSON (schéma v1.0 — consommé par baracca_import.py)
   env = catalogues du back-office (avec repli sur les valeurs par défaut)
================================================================= */
function buildExport(d, env) {
  const murDef = env.murs.find((m) => m.id === d.murType) || env.murs[0];
  const plancherDef = env.planchers.find((p) => p.id === d.plancher) || env.planchers[0];
  const facadeDef = env.facades.find((f) => f.id === (murDef.surface ?? d.facade)) || env.facades[0];
  const toitureDef = toitureDefOf(d, env);
  const toitType = toitTypeOf(d, env);
  const surfaceDef = env.facades.find((f) => f.id === murDef.surface) || env.facades[0];
  const mursData = {};
  FACES.forEach((f) => {
    const face = d.murs[f.key];
    mursData[f.key] = {
      actif: face.actif,
      type: murDef.nom,
      epaisseur: murDef.epaisseur,
      materiau: murDef.materiau ?? surfaceDef.nom,
      surface: (env.facades.find((f) => f.id === (face.surface ?? murDef.surface)) || env.facades[0]).nom, // surface choisie pour ce mur
      favori: murDef.favori ?? "", // favori Archicad pour ce mur
      longueur: murLongueur(d, f.key),
      hauteur: d.hauteur,
      facade: d.facade,
      ouvertures: face.ouvertures.map((o, i) => ({
        id: `${d.nom}_OUV_${f.key.toUpperCase()}_${i + 1}`,
        type: o.type,
        largeur: o.largeur,
        hauteur: o.hauteur,
        position: o.position,
        favori: env.menuiseries?.find((x) => x.id === o.menuiserieId)?.favori ?? "", // favori Archicad de la menuiserie
      })),
    };
  });
  return {
    schemaVersion: "1.0",
    cible: "Archicad JSON API + Tapir",
    module: {
      nom: d.nom,
      dimensions: { longueur: d.longueur, largeur: d.largeur, hauteur: d.hauteur },
      plancher: {
        nom: plancherDef.nom,
        favori: plancherDef.favori ?? "", // favori Archicad pour le plancher
        epaisseur: +epaisseurPlancher(d, env.planchers).toFixed(3),
        // le plancher est 21,7 cm plus large que les murs, en prenant
        // référence la ligne directrice des murs (de chaque côté)
        longueur: +(d.longueur + 2 * PLANCHER_DEPASSEMENT).toFixed(3),
        largeur: +(d.largeur + 2 * PLANCHER_DEPASSEMENT).toFixed(3),
      },
      facade: {
        nom: facadeDef.nom,
        favori: facadeDef.favori ?? "", // favori Archicad pour la façade
      },
      toiture: {
        type: toitureDef.nom,
        geometrie: toitType, // monopente / deuxpentes / terrasse
        favori: toitureDef.favori ?? "", // favori Archicad pour la toiture
        surface: (env.facades.find((f) => f.id === (d.surfaceToiture ?? toitureDef.surface)) || env.facades[0]).nom, // surface choisie pour la toiture
        epaisseur: +(+(toitureDef.epaisseur ?? 0.30)).toFixed(3),
        pente: toitType === "terrasse" ? null : d.pente,
        pignon: toitType === "deuxpentes" ? d.pignon : null,
        hauteurFaitage: +hauteurFaitage(d, toitType).toFixed(2),
        surface: +surfaceToiture(d, toitType).toFixed(2),
      },
      murs: mursData,
    },
    metriques: {
      surfacePlancher: +surfacePlancher(d).toFixed(2), // ligne directrice des murs
      empriseAuSol: +empriseAuSol(d, murDef).toFixed(2), // surface plancher + épaisseur des murs
      surfaceToiture: +surfaceToiture(d, toitType).toFixed(2),
      surfaceMenuiserie: +FACES.reduce((s, f) => s + surfaceMenuiserie(d.murs[f.key]), 0).toFixed(2),
      volumeBrut: +(surfacePlancher(d) * d.hauteur).toFixed(2),
      // Ajout rétro-compatible : détail des tarifs (catalogue back-office)
      tarif: Object.fromEntries(
        Object.entries(tarifTotal(d, env)).map(([k, v]) => [k, +v.toFixed(2)])
      ),
    },
  };
}

/* Fusion d'une configuration enregistrée avec les valeurs par défaut
   (tolère l'évolution du schéma entre deux versions) */
function mergeConfig(saved) {
  const base = JSON.parse(JSON.stringify(DEFAULTS));
  const out = { ...base, ...saved };
  out.murs = {};
  FACES.forEach((f) => {
    out.murs[f.key] = { ...base.murs[f.key], ...((saved.murs || {})[f.key] || {}) };
  });
  return out;
}

/* =================================================================
   Plan SVG (vue de dessus)
================================================================= */
function Plan({ d, murs, toitType, selectedFace, onSelectFace }) {
  const W = 560, H = 400, margin = 60;
  const murDef = murs.find((m) => m.id === d.murType) || murs[0];
  // Ligne directrice à l'INTÉRIEUR : le rectangle longueur × largeur est la
  // face intérieure des murs (ligne directrice en pointillés) et l'épaisseur
  // du mur est rejetée vers l'EXTÉRIEUR du module.
  const scale = (Math.min(W, H) - 2 * margin) / (Math.max(d.longueur, d.largeur) + 2 * murDef.epaisseur);
  const L = d.longueur * scale, l = d.largeur * scale;
  const x0 = (W - L) / 2, y0 = (H - l) / 2;
  const ep = Math.max(murDef.epaisseur * scale, 4);
  const facePath = {
    nord: { x: x0, y: y0 - ep, w: L, h: ep, horiz: true },
    sud: { x: x0, y: y0 + l, w: L, h: ep, horiz: true },
    ouest: { x: x0 - ep, y: y0 - ep, w: ep, h: l + 2 * ep, horiz: false },
    est: { x: x0 + L, y: y0 - ep, w: ep, h: l + 2 * ep, horiz: false },
  };
  const labelPos = {
    nord: [x0 + L / 2, y0 - ep - 12],
    sud: [x0 + L / 2, y0 + l + ep + 18],
    ouest: [x0 - ep - 12, y0 + l / 2],
    est: [x0 + L + ep + 12, y0 + l / 2],
  };
  const dim = ep + 28;
  return (
    <svg viewBox={`0 0 ${W} ${H}`} style={{ width: "100%", height: "auto" }}>
      <defs>
        <pattern id="hatch" width="6" height="6" patternUnits="userSpaceOnUse">
          <path d="M0 6 L6 0" stroke="#c8b8a5" strokeWidth="1" />
        </pattern>
      </defs>
      <rect x={x0} y={y0} width={L} height={l} fill="#faf7f2" stroke="#57534e" strokeWidth="1" strokeDasharray="6 4" />
      <text x={x0 + L / 2} y={y0 + l / 2 + (toitType === "deuxpentes" ? 18 : 4)} textAnchor="middle" fill="#a8a29e" fontSize="11">
        {fmtCm(d.longueur)} × {fmtCm(d.largeur)} cm — {surfacePlancher(d).toFixed(1)} m²
      </text>
      {FACES.map((f) => {
        const p = facePath[f.key];
        const isSel = selectedFace === f.key;
        const face = d.murs[f.key];
        return (
          <g key={f.key} onClick={() => onSelectFace(f.key)} style={{ cursor: "pointer" }}>
            <rect
              x={p.x} y={p.y} width={p.w} height={p.h}
              fill={face.actif ? rgbHex(murDef.rgb) : "#e5e5e5"}
              stroke={isSel ? "#b45309" : "#78716c"}
              strokeWidth={isSel ? 3 : 1}
            />
            {!face.actif && <rect x={p.x} y={p.y} width={p.w} height={p.h} fill="url(#hatch)" />}
            {face.actif && face.ouvertures.map((o, i) => {
              const ow = o.largeur * scale;
              const oh = Math.max(ep + 8, 14);
              const ox = p.horiz ? x0 + o.position * scale : p.x + (p.w - oh) / 2;
              const oy = p.horiz ? p.y + (p.h - oh) / 2 : y0 + o.position * scale;
              return (
                <rect key={i} x={ox} y={oy}
                  width={p.horiz ? ow : oh} height={p.horiz ? oh : ow}
                  fill={o.type === "porte" ? "#7c2d12" : "#0284c7"} opacity="0.85" rx="2" />
              );
            })}
            <text className="face-label"
              x={labelPos[f.key][0]} y={labelPos[f.key][1]} textAnchor="middle"
              fill={isSel ? "#b45309" : "#78716c"}>
              {f.label} {face.actif ? `(${face.ouvertures.length} ouvrant${face.ouvertures.length > 1 ? "s" : ""})` : "(désactivé)"}
            </text>
          </g>
        );
      })}
      <line x1={x0} y1={y0 - dim} x2={x0 + L} y2={y0 - dim} stroke="#a8a29e" strokeDasharray="4 3" />
      <text x={x0 + L / 2} y={y0 - dim - 4} textAnchor="middle" fill="#78716c" fontSize="10">{fmtCm(d.longueur)} cm</text>
      <line x1={x0 - dim} y1={y0} x2={x0 - dim} y2={y0 + l} stroke="#a8a29e" strokeDasharray="4 3" />
      <text x={x0 - dim - 4} y={y0 + l / 2} textAnchor="middle" fill="#78716c" fontSize="10"
        transform={`rotate(-90 ${x0 - dim - 4} ${y0 + l / 2})`}>{fmtCm(d.largeur)} cm</text>
      {toitType === "deuxpentes" && (
        <g>
          <line x1={x0} y1={y0 + l / 2} x2={x0 + L} y2={y0 + l / 2}
            stroke="#b45309" strokeWidth="2" strokeDasharray="7 5" />
          <text x={x0 + L / 2} y={y0 + l / 2 - 6} textAnchor="middle" fill="#b45309" fontSize="10">
            Faitage {d.pignon ? "(pignon)" : "(croupe)"}
          </text>
        </g>
      )}
    </svg>
  );
}

/* =================================================================
   Élévation SVG (façade sud)
================================================================= */
function Elevation({ d, murs, planchers, surfaces, toiture, faceKey }) {
  const W = 560, H = 320, margin = 60;
  const horiz = faceKey === "nord" || faceKey === "sud";
  const Lm = horiz ? d.longueur : d.largeur;
  const face = d.murs[faceKey];
  const murDef = murs.find((m) => m.id === d.murType) || murs[0];
  const epP = epaisseurPlancher(d, planchers);
  const toitType = toiture.type || "monopente";
  const epToit = +(toiture.epaisseur ?? 0.30);   // épaisseur de la toiture (m)
  const toitRgb = toiture.rgb || [120, 113, 108];
  const surfaceDef = surfaces.find((s) => s.id === (d.murs[faceKey]?.surface ?? murDef.surface)) || surfaces[0];
  // Échelle UNIQUE en horizontal et en vertical (les proportions largeur /
  // hauteur sont conservées), en réservant la place de la toiture, du
  // plancher et du sol. Ligne directrice des murs à l'intérieur : la façade
  // dessinée s'étend de l'épaisseur du mur au-delà de la ligne directrice,
  // de chaque côté (une menuiserie à 7,8 cm de la ligne directrice apparaît
  // donc à 7,8 cm + épaisseur du mur du bord extérieur de l'élévation).
  const scale = Math.min(
    (W - 2 * margin) / (Lm + 2 * murDef.epaisseur),
    (H - 2 * margin) / Math.max(d.hauteur + hauteurFaitage(d, toitType) + epToit + epP + SOL_SOUS_PLANCHER, 3)
  );
  const epPx = murDef.epaisseur * scale;
  const epPxP = epP * scale;                      // épaisseur du plancher en px
  const epToitPx = epToit * scale;                // épaisseur de la toiture en px
  const depPx = PLANCHER_DEPASSEMENT * scale;     // dépassement du plancher / ligne directrice des murs
  const L = Lm * scale;
  const hM = d.hauteur * scale, hF = hauteurFaitage(d, toitType) * scale;
  const x0 = (W - L) / 2;
  const ySol = H - margin;                                     // trait du sol
  const yPlancher = ySol - SOL_SOUS_PLANCHER * scale - epPxP;  // ligne directrice du plancher = bas des murs
  const yTop = yPlancher - hM;                                 // ligne directrice de la toiture (bas de la toiture)
  const nom = FACES.find((f) => f.key === faceKey).label;
  // Vue extérieure : miroir vu du NORD (regard vers le sud : l'ouest
  // apparaît à droite) et vu de l'EST (regard vers l'ouest : le sud
  // apparaît à droite, or les positions sont mesurées depuis le coin sud).
  // Pas de miroir pour sud et ouest.
  const miroir = faceKey === "nord" || faceKey === "est";
  const ox = (o) => x0 + (miroir ? L - (o.position + o.largeur) * scale : o.position * scale);
  const xa = x0 - epPx, xb = x0 + L + epPx;   // bords extérieurs de la façade
  const pa = x0 - depPx, pb = x0 + L + depPx; // bords du plancher (21,7 cm au-delà de la ligne directrice)
  let toitPath, toitFill = "none", toitDash = null, vueCote = false;
  if (toitType === "terrasse") {
    toitPath = `M ${xa} ${yTop} L ${xb} ${yTop}`;
  } else if (toitType === "monopente") {
    if (horiz) {
      toitPath = `M ${xa} ${yTop} L ${xb} ${yTop} M ${xa} ${yTop - hF} L ${xb} ${yTop - hF}`;
      toitDash = "8 5"; vueCote = true;
    } else {
      // monopente (gouttière au sud, faitage au nord) :
      // vu de l'OUEST (nord à gauche) -> haut à gauche ;
      // vu de l'EST (nord à droite) -> haut à droite.
      toitPath = faceKey === "ouest"
        ? `M ${xa} ${yTop - hF} L ${xb} ${yTop}`
        : `M ${xa} ${yTop} L ${xb} ${yTop - hF}`;
    }
  } else if (horiz) {
    toitPath = `M ${xa} ${yTop} L ${xb} ${yTop} M ${xa} ${yTop - hF} L ${xb} ${yTop - hF}`;
    toitDash = "8 5"; vueCote = true;
  } else {
    toitPath = `M ${xa} ${yTop} L ${x0 + L / 2} ${yTop - hF} L ${xb} ${yTop} Z`;
    toitFill = d.pignon ? "rgba(180,83,9,0.15)" : "none";
  }
  return (
    <svg viewBox={`0 0 ${W} ${H}`} style={{ width: "100%", height: "auto" }}>
      <line x1={20} y1={ySol} x2={W - 20} y2={ySol} stroke="#a8a29e" strokeWidth="2" />
      {/* plancher : ligne directrice en haut (point bas des murs), épaisseur vers le bas,
          bords à 21,7 cm au-delà de la ligne directrice des murs */}
      <rect x={pa} y={yPlancher} width={L + 2 * depPx} height={epPxP}
        fill="#e7e5e4" stroke="#78716c" />
      <line x1={pa} y1={yPlancher} x2={pb} y2={yPlancher} stroke="#57534e" strokeWidth="1" strokeDasharray="5 4" />
      <defs>
        {surfaceDef?.image && (
          <pattern id="murSurf" patternUnits="userSpaceOnUse" x={xa} y={yTop} width={L + 2 * epPx} height={hM}>
            <image href={surfaceDef.image} x={xa} y={yTop} width={L + 2 * epPx} height={hM} preserveAspectRatio="xMidYMid slice" />
          </pattern>
        )}
      </defs>
      <rect x={xa} y={yTop} width={L + 2 * epPx} height={hM}
        fill={surfaceDef?.image ? "url(#murSurf)" : rgbHex(murDef.rgb)} opacity="0.85" stroke="#78716c" />
      {/* ligne directrice des murs (intérieure), à l'épaisseur du mur du bord extérieur */}
      <line x1={x0} y1={yTop} x2={x0} y2={yPlancher} stroke="#57534e" strokeWidth="1" strokeDasharray="5 4" />
      <line x1={x0 + L} y1={yTop} x2={x0 + L} y2={yPlancher} stroke="#57534e" strokeWidth="1" strokeDasharray="5 4" />
      {face.actif && face.ouvertures.map((o, i) => (
        <rect key={i} x={ox(o)} y={yPlancher - o.hauteur * scale}
          width={o.largeur * scale} height={o.hauteur * scale}
          fill={o.type === "porte" ? "#7c2d12" : "#0284c7"} opacity="0.85" rx="2" />
      ))}
      {/* toiture : bande d'épaisseur epToitPx AU-DESSUS de sa ligne directrice (en bas, yTop) */}
      <g transform={`translate(0 ${-epToitPx / 2})`}>
        <path d={toitPath} stroke={rgbHex(toitRgb)} strokeWidth={Math.max(epToitPx, 3)} fill={toitFill} strokeDasharray={toitDash} strokeLinejoin="round" />
      </g>
      {!face.actif && (
        <text x={x0 + L / 2} y={yTop + hM / 2} textAnchor="middle" fill="#a8a29e" fontSize="11">Mur désactivé</text>
      )}
      <text x={x0 + L / 2} y={yTop - hF - 12} textAnchor="middle" fill="#78716c" fontSize="11">
        {toitType === "terrasse"
          ? `${nom} — terrasse plate`
          : vueCote
            ? `${nom} — toiture vue de côté (faitage +${fmtCm(hauteurFaitage(d, toitType))} cm)`
            : `${nom} — ${d.pignon ? "pignon" : "croupe"}, faitage +${fmtCm(hauteurFaitage(d, toitType))} cm`}
      </text>
    </svg>
  );
}

/* =================================================================
   Petits composants UI
================================================================= */
const Field = ({ label, children }) => (
  <div className="field">
    <label>{label}</label>
    {children}
  </div>
);

/* =================================================================
   App
================================================================= */
export default function App() {
  const [d, setD] = useState(DEFAULTS);
  const [selectedFace, setSelectedFace] = useState("sud");
  const [tab, setTab] = useState("module");
  const [flash, setFlash] = useState("");
  const [caracOpen, setCaracOpen] = useState(false); // zone « Caractéristiques techniques » fermée par défaut
  const [chiffrageOpen, setChiffrageOpen] = useState(false); // détail par mur du chiffrage, fermé par défaut
  const [addOpen, setAddOpen] = useState(false);     // zone d'ajout d'un ouvrant (sous le +)
  const [addMenuiserieId, setAddMenuiserieId] = useState("");
  const [addError, setAddError] = useState("");

  /* ----- Catalogues du back-office (menuiseries, modules, enveloppe) ----- */
  const [catalogue, setCatalogue] = useState(null);

  /* ----- Espace client : session, configurations enregistrées ----- */
  const [session, setSession] = useState(null); // null = chargement en cours
  const [configs, setConfigs] = useState([]);
  const [saveName, setSaveName] = useState("");
  const [configMsg, setConfigMsg] = useState("");
  const [submitting, setSubmitting] = useState(false);

  const set = (patch) => setD((prev) => ({ ...prev, ...patch }));
  const setFace = (key, patch) =>
    setD((prev) => ({ ...prev, murs: { ...prev.murs, [key]: { ...prev.murs[key], ...patch } } }));

  /* Catalogues du back-office, avec repli sur les valeurs par défaut */
  const env = {
    murs: catalogue?.enveloppe?.murs?.length ? catalogue.enveloppe.murs : MURS,
    planchers: catalogue?.enveloppe?.planchers?.length ? catalogue.enveloppe.planchers : PLANCHERS,
    facades: catalogue?.enveloppe?.surfaces?.length ? catalogue.enveloppe.surfaces : FACADES,
    menuiseries: catalogue?.menuiseries?.length ? catalogue.menuiseries : null,
    modules: catalogue?.modules?.length ? catalogue.modules : null,
    toitures: catalogue?.toitures?.length ? catalogue.toitures : TOITURES,
  };

  const face = d.murs[selectedFace];
  const toitureDef = toitureDefOf(d, env);
  const toitType = toitTypeOf(d, env);
  const toitureNom = toitureDef.nom;
  const murSel = env.murs.find((m) => m.id === d.murType);
  const moduleSel = env.modules?.find((m) => m.id === d.moduleId);
  const surfaceSel = env.facades.find((f) => f.id === murSel?.surface) || env.facades[0];
  const surfaceNom = (id) => env.facades.find((f) => f.id === id)?.nom || "—";
  const plancherSel = env.planchers.find((p) => p.id === d.plancher);
  const json = useMemo(() => JSON.stringify(buildExport(d, env), null, 2), [d, catalogue]); // eslint-disable-line react-hooks/exhaustive-deps
  const tarifs = useMemo(() => tarifTotal(d, env), [d, catalogue]); // eslint-disable-line react-hooks/exhaustive-deps
  const surfaces = useMemo(() => surfacesChiffrage(d, env), [d, catalogue]); // eslint-disable-line react-hooks/exhaustive-deps

  /* Patch d'état appliqué au choix d'un modèle de module du catalogue :
     géométrie prédéfinie + éléments d'enveloppe liés au modèle
     (le client ne peut ni l'un ni l'autre — affichage en lecture seule) */
  const modulePatch = (m) => ({
    moduleId: m.id,
    nom: m.nom,
    longueur: +m.longueur,
    largeur: +m.largeur,
    hauteur: +m.hauteur,
    toiture: m.toiture,
    pente: +m.pente || 15,
    pignon: m.pignon !== false,
    ...(m.mur ? { murType: m.mur } : {}),
    ...(m.plancher ? { plancher: m.plancher } : {}),
    // la surface extérieure est celle du mur choisi (mur.surface)
  });

  const applyModule = (id) => {
    const m = env.modules?.find((x) => x.id === id);
    set(m ? modulePatch(m) : { moduleId: null });
  };

  const ouvrirAjout = () => {
    setAddMenuiserieId(env.menuiseries?.[0]?.id || "");
    setAddError("");
    setAddOpen(true);
  };
  const validerAjout = () => {
    const def = env.menuiseries?.find((x) => x.id === addMenuiserieId);
    const largeur = def ? +def.largeur : 1.0;
    const hauteur = def ? +def.hauteur : 2.1;
    const L = murLongueur(d, selectedFace);
    // Place occupée = fin de la dernière ouverture
    const occupe = face.ouvertures.reduce((mx, o) => Math.max(mx, o.position + o.largeur), 0);
    // Position calée sur la grille 7,8 cm + n × 60 cm, au moins après la dernière ouverture
    const k = Math.max(0, Math.ceil((occupe - POSITION_MIN) / POSITION_STEP - 1e-9));
    const position = Math.round((POSITION_MIN + k * POSITION_STEP) * 1000) / 1000;
    // Vérification préalable : la place restante sur le mur suffit-elle ?
    if (position + largeur > L + 1e-9) {
      setAddError(`Plus de place sur ce mur (${fmtCm(L)} cm) pour cet ouvrant (${fmtCm(largeur)} cm) à partir de ${fmtCm(position)} cm.`);
      return;
    }
    setFace(selectedFace, {
      ouvertures: [
        ...face.ouvertures,
        { menuiserieId: def?.id ?? null, type: def?.type || "baie", largeur, hauteur, position },
      ],
    });
    setAddOpen(false);
    setAddError("");
  };
  const updateOuverture = (i, patch) =>
    setFace(selectedFace, { ouvertures: face.ouvertures.map((o, j) => (j === i ? { ...o, ...patch } : o)) });
  const pickMenuiserie = (i, menuiserieId) => {
    const m = env.menuiseries?.find((x) => x.id === menuiserieId);
    updateOuverture(i, m
      ? { menuiserieId: m.id, type: m.type, largeur: +m.largeur, hauteur: +m.hauteur }
      : { menuiserieId: null });
  };
  /* Position maximale : l'ouverture reste entièrement dans le mur */
  const positionMax = (o) => Math.max(POSITION_MIN, murLongueur(d, selectedFace) - o.largeur);
  /* Avance/recule par pas de 60 cm, borné entre 7,8 cm et le maximum */
  const stepPosition = (i, dir) => {
    const o = face.ouvertures[i];
    const next = Math.round((o.position + dir * POSITION_STEP) * 1000) / 1000;
    updateOuverture(i, { position: Math.min(Math.max(next, POSITION_MIN), positionMax(o)) });
  };
  const removeOuverture = (i) =>
    setFace(selectedFace, { ouvertures: face.ouvertures.filter((_, j) => j !== i) });

  const nbErreurs = FACES.reduce(
    (n, f) => n + d.murs[f.key].ouvertures.filter((o) => !validerOuverture(d, f.key, o)).length, 0
  );

  const notifier = (msg) => { setFlash(msg); setTimeout(() => setFlash(""), 2000); };

  /* ----- Espace client : session, configurations enregistrées ----- */
  const postConfigs = async (payload) => {
    const r = await fetch(API_CONFIGS, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    return r.json();
  };

  const refreshConfigs = async () => {
    try {
      const l = await postConfigs({ action: "list" });
      if (l.ok) setConfigs(l.configurations);
    } catch { /* silencieux */ }
  };

  // Au chargement : état de la session, liste des configurations,
  // et chargement éventuel d'une configuration demandée (?config=<id>)
  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const r = await fetch(API_SESSION);
        const s = await r.json();
        if (cancelled) return;
        setSession(s);
        if (!s.loggedIn) {
          // Enregistrement automatique local restauré (visiteur non connecté, pas de ?config=)
          try {
            const auto = localStorage.getItem("baracca_autosave");
            if (auto && !new URLSearchParams(window.location.search).get("config")) {
              const saved = JSON.parse(auto);
              if (saved && saved.config) {
                setD(mergeConfig(saved.config));
                notifier("Brouillon restauré ✓");
              }
            }
          } catch { /* silencieux */ }
          return;
        }
        const l = await postConfigs({ action: "list" });
        if (cancelled) return;
        if (l.ok) setConfigs(l.configurations);
        const wanted = new URLSearchParams(window.location.search).get("config");
        if (wanted) {
          const g = await postConfigs({ action: "get", id: wanted });
          if (!cancelled && g.ok && g.configuration) {
            setD(mergeConfig(g.configuration.config));
            notifier("Configuration chargée ✓");
          }
        }
      } catch {
        if (!cancelled) setSession({ loggedIn: false, user: null });
      }
    })();
    return () => { cancelled = true; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Enregistrement automatique avant de quitter la page (changement de page / fermeture) :
  // compte client -> sauvegarde serveur ; sinon -> brouillon local (localStorage)
  useEffect(() => {
    const autosave = () => {
      try {
        const payload = { action: "save", nom: (d.nom || "module_baracca").trim(), config: d, json };
        if (session?.loggedIn) {
          fetch(API_CONFIGS, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
            keepalive: true,
          }).catch(() => {});
        } else {
          localStorage.setItem("baracca_autosave", JSON.stringify({ config: d, json, at: Date.now() }));
        }
      } catch { /* silencieux : le brouillon ne doit pas bloquer la navigation */ }
    };
    window.addEventListener("pagehide", autosave);
    window.addEventListener("beforeunload", autosave);
    return () => {
      window.removeEventListener("pagehide", autosave);
      window.removeEventListener("beforeunload", autosave);
    };
  }, [d, json, session]);

  // Chargement des catalogues (menuiseries, modèles de modules, enveloppe)
  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const r = await fetch(API_CATALOGUE);
        const g = await r.json();
        if (!cancelled && g.ok && g.catalogue) setCatalogue(g.catalogue);
      } catch { /* repli sur les catalogues par défaut */ }
    })();
    return () => { cancelled = true; };
  }, []);

  // Réconcilie l'état courant si le catalogue back-office a évolué
  // (ex. : type de mur supprimé depuis le back-office)
  useEffect(() => {
    if (!catalogue) return;
    setD((prev) => {
      const e = catalogue.enveloppe || {};
      const patch = {};
      if (e.murs?.length && !e.murs.some((m) => m.id === prev.murType)) patch.murType = e.murs[0].id;
      if (e.planchers?.length && !e.planchers.some((p) => p.id === prev.plancher)) patch.plancher = e.planchers[0].id;
      if (e.surfaces?.length && !e.surfaces.some((f) => f.id === prev.facade)) patch.facade = e.surfaces[0].id;
      if (prev.moduleId && catalogue.modules?.length && !catalogue.modules.some((m) => m.id === prev.moduleId)) {
        patch.moduleId = null;
      }
      // Visite sans configuration demandée : le configurateur démarre sur le
      // premier modèle du catalogue (il n'existe pas de module « sur mesure »)
      const wantsConfig = new URLSearchParams(window.location.search).get("config");
      if (!wantsConfig && !prev.moduleId && catalogue.modules?.length) {
        Object.assign(patch, modulePatch(catalogue.modules[0]));
      }
      return Object.keys(patch).length ? { ...prev, ...patch } : prev;
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [catalogue]);

  const enregistrer = async () => {
    const nom = (saveName || d.nom || "module_baracca").trim();
    try {
      const g = await postConfigs({ action: "save", nom, config: d, json });
      if (g.ok) {
        setConfigMsg("Configuration enregistrée ✓");
        setSaveName("");
        await refreshConfigs();
      } else {
        setConfigMsg(g.error || "Erreur lors de l'enregistrement.");
      }
    } catch {
      setConfigMsg("Erreur réseau lors de l'enregistrement.");
    }
    setTimeout(() => setConfigMsg(""), 2500);
  };

  const chargerConfig = async (id) => {
    try {
      const g = await postConfigs({ action: "get", id });
      if (g.ok && g.configuration) {
        setD(mergeConfig(g.configuration.config));
        setTab("module");
        notifier("Configuration chargée ✓");
      }
    } catch { notifier("Chargement impossible"); }
  };

  const supprimerConfig = async (id) => {
    try {
      const g = await postConfigs({ action: "delete", id });
      if (g.ok) {
        await refreshConfigs();
        notifier("Configuration supprimée ✓");
      }
    } catch { notifier("Suppression impossible"); }
  };

  // Bouton « Soumettre » : envoie la configuration à Baracca
  const soumettre = async () => {
    setSubmitting(true);
    try {
      const r = await fetch(API_SOUMETTRE, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: json,
      });
      const g = await r.json();
      if (g.ok) {
        notifier(g.mail === false
          ? "Dossier enregistré, mais la notification e-mail a échoué"
          : "Dossier soumis — l'architecte est averti ✓");
      } else {
        notifier(g.error || "Échec de la soumission");
      }
    } catch {
      notifier("Échec de la soumission");
    }
    setSubmitting(false);
  };

  const tabs = [
    ["module", "Module"],
    ["murs", "Murs"],
    ["toiture", "Toiture"],
    ["ouvrants", "Ouvrants"],
  ];

  return (
    <div className="app">
      <header className="top">
        <div>
          <h1>Configurateur de modules Baracca</h1>
          <p>Configurez un module sur mesure et exportez les données vers Archicad (JSON API + Tapir)</p>
        </div>
        <span className={"badge" + (nbErreurs ? " error" : "")}>
          {nbErreurs === 0 ? "Configuration valide" : `${nbErreurs} erreur(s)`}
        </span>
      </header>

      <div className="layout">
        <div className="layout-left">
        <div className="card">
          <div className="card-title">Plan du module (cliquez un mur pour l'éditer)</div>
          <div className="card-body"><Plan d={d} murs={env.murs} toitType={toitType} selectedFace={selectedFace} onSelectFace={setSelectedFace} /></div>
        </div>
        <div className="card">
          <div className="card-title">Élévation — {FACES.find((f) => f.key === selectedFace).label}</div>
          <div className="card-body">
            <p style={{ fontSize: 12, color: "#78716c", margin: "0 0 6px" }}>
              Cliquez une face sur le plan (ou ci-dessous) pour changer d'élévation.
            </p>
            <div style={{ display: "flex", gap: 4, marginBottom: 10 }}>
              {FACES.map((f) => (
                <button key={f.key}
                  onClick={() => setSelectedFace(f.key)}
                  style={{
                    fontSize: 12, padding: "4px 10px", borderRadius: 6, cursor: "pointer",
                    border: "1px solid", fontWeight: 500,
                    background: selectedFace === f.key ? "#b45309" : "transparent",
                    color: selectedFace === f.key ? "#fff" : "#78716c",
                    borderColor: selectedFace === f.key ? "#b45309" : "#d6d3d1",
                  }}>
                  {f.label.replace("Mur ", "")}
                </button>
              ))}
            </div>
            <Elevation d={d} murs={env.murs} planchers={env.planchers} surfaces={env.facades} toiture={toitureDef} faceKey={selectedFace} />
          </div>
        </div>
        </div>
        <div className="layout-right">
      <div className="card">
        <div className="card-title">Réglages</div>
        <div className="card-body">
          <div className="tabs">
            {tabs.map(([key, label]) => (
              <button key={key} className={"tab-btn" + (tab === key ? " active" : "")}
                onClick={() => setTab(key)}>{label}</button>
            ))}
          </div>
          <div className="reglage-grid">
          <div className="reglage-main">
          {tab === "module" && (
            <>
              <div className="field field-module-name">
                <input type="text" value={d.nom} onChange={(e) => set({ nom: e.target.value })} placeholder="Nom du module" />
              </div>
              <div className="field" style={{ marginTop: 12 }}>
                <label>Modèle de module (catalogue)</label>
                <select value={d.moduleId || ""} onChange={(e) => applyModule(e.target.value)}>
                  {!d.moduleId && <option value="">— Choisir un module —</option>}
                  {env.modules?.map((m) => {
                    const modToit = env.toitures.find((t) => t.id === m.toiture);
                    const modMur = env.murs.find((x) => x.id === m.mur);
                    const modSurf = env.facades.find((f) => f.id === modMur?.surface);
                    const surf = (m.longueur * m.largeur).toLocaleString("fr-FR", { maximumFractionDigits: 1 });
                    return (
                      <option key={m.id} value={m.id}>
                        {m.nom} — {surf} m² — {modToit?.nom || m.toiture} — {modSurf?.nom || "—"}
                      </option>
                    );
                  })}
                </select>
              </div>
            </>
          )}
          </div>
          <div className="reglage-side">
            {moduleSel?.image
              ? <img src={moduleSel.image} alt={moduleSel.nom || "Module"} className="reglage-visual-img" />
              : <div className="reglage-visual-empty">—</div>}
          </div>
          </div>

          {tab === "module" && (
            <>
              <hr className="sep" />
              <p className="section-label carac-toggle" onClick={() => setCaracOpen((o) => !o)}>
                <span className={"carac-arrow" + (caracOpen ? " open" : "")}></span>
                Caractéristiques techniques
              </p>
              {caracOpen && (
              <div className="spec">
                <div className="spec-row"><span className="spec-k">Longueur × largeur</span><span className="spec-v">{fmtCm(d.longueur)} × {fmtCm(d.largeur)} cm</span></div>
                <div className="spec-row"><span className="spec-k">Hauteur sous plafond</span><span className="spec-v">{fmtCm(d.hauteur)} cm</span></div>
                <div className="spec-row">
                  <span className="spec-k">Toiture</span>
                  <span className="spec-v">
                    {toitureNom}
                    {toitType !== "terrasse" && ` — pente ${d.pente}°`}
                    {toitType === "deuxpentes" && (d.pignon ? " — pignon" : " — croupe")}
                  </span>
                </div>
                <div className="spec-row">
                  <span className="spec-k">Murs</span>
                  <span className="spec-v">{murSel?.nom || "—"}{surfaceSel ? ` — surface extérieure : ${surfaceSel.nom}` : ""}</span>
                </div>
                <div className="spec-row">
                  <span className="spec-k">Plancher</span>
                  <span className="spec-v">{plancherSel?.nom || "—"}</span>
                </div>
              </div>
              )}
              <div className="metrics">
                <div className="metric"><div className="k">Surface plancher</div><div className="v">{surfacePlancher(d).toFixed(1)} m²</div></div>
                <div className="metric"><div className="k">Emprise au sol</div><div className="v">{empriseAuSol(d, murSel || env.murs[0]).toFixed(1)} m²</div></div>
                <div className="metric"><div className="k">Surface toiture</div><div className="v">{surfaceToiture(d, toitType).toFixed(1)} m²</div></div>
                <div className="metric"><div className="k">Volume brut</div><div className="v">{(surfacePlancher(d) * d.hauteur).toFixed(1)} m³</div></div>
                <div className="metric"><div className="k">Menuiseries</div><div className="v">{FACES.reduce((s, f) => s + surfaceMenuiserie(d.murs[f.key]), 0).toFixed(2)} m²</div></div>
              </div>
            </>
          )}

          {tab === "murs" && (
            <>
              <p className="config-hint" style={{ marginTop: 0 }}>
                Surface appliquée à chaque mur (définie dans le back-office, onglet « Surfaces »).
              </p>
              <div className="fields-grid">
                {FACES.map((f) => {
                  const defSurf = surfaceNom(d.murs[f.key].surface ?? murSel?.surface ?? "");
                  return (
                    <Field key={f.key} label={f.label}>
                      <select
                        value={d.murs[f.key].surface ?? murSel?.surface ?? ""}
                        onChange={(e) => setFace(f.key, { surface: e.target.value || null })}
                      >
                        <option value="">Par défaut — {defSurf}</option>
                        {env.facades.map((s) => (
                          <option key={s.id} value={s.id}>{s.nom}</option>
                        ))}
                      </select>
                    </Field>
                  );
                })}
              </div>
            </>
          )}

          {tab === "toiture" && (
            <>
              <p className="config-hint" style={{ marginTop: 0 }}>
                Surface appliquée à la toiture (définie dans le back-office, onglet « Surfaces »).
              </p>
              <div className="fields-grid">
                <Field label="Toiture">
                  <select
                    value={d.surfaceToiture ?? toitureDef.surface ?? ""}
                    onChange={(e) => set({ surfaceToiture: e.target.value || null })}
                  >
                    <option value="">Par défaut — {surfaceNom(toitureDef.surface ?? "")}</option>
                    {env.facades.map((s) => (
                      <option key={s.id} value={s.id}>{s.nom}</option>
                    ))}
                  </select>
                </Field>
              </div>
            </>
          )}

          {tab === "ouvrants" && (
            <>
              <div className="face-picker">
                {FACES.map((f) => (
                  <button key={f.key}
                    className={"btn small" + (selectedFace === f.key ? " primary" : "")}
                    onClick={() => setSelectedFace(f.key)}>{f.label}</button>
                ))}
              </div>
              <label className="checkline">
                <input type="checkbox" checked={face.actif}
                  onChange={(e) => setFace(selectedFace, { actif: e.target.checked })} />
                {FACES.find((f) => f.key === selectedFace).label.toLowerCase()} présent dans le module
              </label>
              {face.actif && (
                <div style={{ marginBottom: 10, display: "flex", alignItems: "center", gap: 8 }}>
                  <button type="button" className="btn-add" onClick={ouvrirAjout} title="Rajouter un ouvrant">+</button>
                  <span style={{ fontSize: "0.85rem", color: "var(--muted)" }}>rajouter un ouvrant</span>
                </div>
              )}
              {addOpen && face.actif && (
                <div className="ouverture add-zone">
                  <div className="ouverture-main">
                    <div className="menuiserie-row">
                      <div className="fields-grid" style={{ flex: 1 }}>
                        <Field label="Ouvrant (catalogue)">
                          <select value={addMenuiserieId} onChange={(e) => setAddMenuiserieId(e.target.value)}>
                            {env.menuiseries?.map((x) => (
                              <option key={x.id} value={x.id}>
                                {x.nom} — {fmtCm(x.largeur)} × {fmtCm(x.hauteur)} cm{+x.tarif > 0 ? ` — ${fmtEur(x.tarif)} HT` : ""}
                              </option>
                            ))}
                          </select>
                        </Field>
                      </div>
                    </div>
                    <button type="button" className="btn primary small" onClick={validerAjout}>Valider</button>
                    <button type="button" className="icon-btn danger" onClick={() => setAddOpen(false)} title="Annuler" aria-label="Annuler">
                      <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="M3.5 3.5l9 9m0-9l-9 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/></svg>
                    </button>
                  </div>
                  {addError && <div className="field-error">{addError}</div>}
                </div>
              )}
              <hr className="sep" />
              {env.menuiseries && face.actif && (
                <p className="config-hint" style={{ marginTop: 0 }}>
                  Choisissez une menuiserie dans le catalogue (type, dimensions et tarif définis dans le
                  back-office), puis sa position sur le mur.
                </p>
              )}
              {face.actif ? (
                <div>
                  {face.ouvertures.map((o, i) => {
                    const m = env.menuiseries?.find((x) => x.id === o.menuiserieId);
                    return (
                    <div key={i} className="ouverture">
                      <div className="ouverture-main">
                      <div className="menuiserie-row">
                        {m?.image && <img src={m.image} alt={m.nom} className="menuiserie-thumb" />}
                        <div className="fields-grid" style={{ flex: 1 }}>
                          {env.menuiseries ? (
                            <Field label="Menuiserie (catalogue)">
                              <select value={o.menuiserieId || ""} onChange={(e) => pickMenuiserie(i, e.target.value)}>
                                <option value="">Ouverture sur mesure…</option>
                                {env.menuiseries.map((x) => (
                                  <option key={x.id} value={x.id}>
                                    {x.nom} — {fmtCm(x.largeur)} × {fmtCm(x.hauteur)} cm{+x.tarif > 0 ? ` — ${fmtEur(x.tarif)} HT` : ""}
                                  </option>
                                ))}
                              </select>
                            </Field>
                          ) : (
                            <Field label="Type">
                              <select value={o.type} onChange={(e) => updateOuverture(i, { type: e.target.value })}>
                                <option value="baie">Baie vitrée</option>
                                <option value="fenetre">Fenêtre</option>
                                <option value="porte">Porte</option>
                              </select>
                            </Field>
                          )}
                          {(!env.menuiseries || !o.menuiserieId) && (
                            <>
                              <Field label="Largeur (cm)">
                                <input type="number" step="0.1" value={Math.round(o.largeur * 1000) / 10}
                                  onChange={(e) => updateOuverture(i, { largeur: Math.round((+e.target.value || 0) * 10) / 1000 })} />
                              </Field>
                              <Field label="Hauteur (cm)">
                                <input type="number" step="0.1" value={Math.round(o.hauteur * 1000) / 10}
                                  onChange={(e) => updateOuverture(i, { hauteur: Math.round((+e.target.value || 0) * 10) / 1000 })} />
                              </Field>
                            </>
                          )}
                          <Field label="position sur le mur">
                            <div className="stepper">
                              <button type="button" className="btn small step-arrow"
                                disabled={o.position - POSITION_STEP < POSITION_MIN - 1e-9}
                                onClick={() => stepPosition(i, -1)} title="Déplacer vers la gauche" aria-label="Déplacer vers la gauche">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M19 12H5m0 0l6-6m-6 6l6 6"/></svg>
                              </button>
                              <button type="button" className="btn small step-arrow"
                                disabled={o.position + POSITION_STEP > positionMax(o) + 1e-9}
                                onClick={() => stepPosition(i, 1)} title="Déplacer vers la droite" aria-label="Déplacer vers la droite">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M5 12h14m0 0l-6-6m6 6l-6 6"/></svg>
                              </button>
                            </div>
                          </Field>
                        </div>
                      </div>
                      <button className="icon-btn danger" onClick={() => removeOuverture(i)} title="Supprimer l'ouverture" aria-label="Supprimer l'ouverture">
                        <svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="M3.5 3.5l9 9m0-9l-9 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/></svg>
                      </button>
                      </div>
                      {!validerOuverture(d, selectedFace, o) && (
                        <div className="field-error">
                          Position autorisée : entre {fmtCm(POSITION_MIN)} cm et {fmtCm(Math.max(0, murLongueur(d, selectedFace) - o.largeur))} cm
                          depuis l'origine — l'ouverture ne doit dépasser ni le mur ({fmtCm(murLongueur(d, selectedFace))} cm)
                          ni la hauteur ({fmtCm(d.hauteur)} cm).
                        </div>
                      )}
                    </div>
                    );
                  })}
                </div>
              ) : (
                <p style={{ fontSize: "0.85rem", color: "#78716c" }}>
                  Mur désactivé — aucun élément ne sera généré pour cette face.
                </p>
              )}
            </>
          )}

        </div>
      </div>
      <div className="card">
        <div className="card-title">Chiffrage estimatif</div>
        <div className="card-body">
          <div className="chiffrage-lines">
            <div className="chiffrage-line"><span className="chiffrage-left"><span className="chiffrage-k">Murs</span><span className="chiffrage-surf">{fmtM2(surfaces.murs)}</span></span><span className="chiffrage-v">{fmtEur(tarifs.mur)} HT</span></div>
            <p className="section-label carac-toggle" onClick={() => setChiffrageOpen((o) => !o)}>
              <span className={"carac-arrow" + (chiffrageOpen ? " open" : "")}></span>
              Détail par mur
            </p>
            {chiffrageOpen && surfaces.mursDetail.map((m) => (
              <div className="chiffrage-line" key={m.key}>
                <span className="chiffrage-left"><span className="chiffrage-k">{m.label}</span><span className="chiffrage-surf">{surfaceNom(m.surfaceId)} - {fmtM2(m.area)}</span></span>
                <span className="chiffrage-right"><span className="chiffrage-v">{fmtEur(m.amount)} HT</span></span>
              </div>
            ))}
            <div className="chiffrage-line"><span className="chiffrage-left"><span className="chiffrage-k">Plancher</span><span className="chiffrage-surf">{fmtM2(surfaces.plancher)}</span></span><span className="chiffrage-v">{fmtEur(tarifs.plancher)} HT</span></div>
            <div className="chiffrage-line"><span className="chiffrage-left"><span className="chiffrage-k">Toiture</span><span className="chiffrage-surf">{fmtM2(surfaces.toiture)}</span></span><span className="chiffrage-v">{fmtEur(tarifs.toiture)} HT</span></div>
            <div className="chiffrage-line"><span className="chiffrage-left"><span className="chiffrage-k">Ouvrants ({surfaces.ouvrants})</span></span><span className="chiffrage-v">{fmtEur(tarifs.menuiseries)} HT</span></div>
          </div>
          <div className="chiffrage-total">
            <div className="chiffrage-row"><span className="k">Coût estimatif</span><span className="v">{fmtEur(tarifs.total)} HT</span></div>
            <div className="chiffrage-row"><span className="k">TVA (20 %)</span><span className="v">{fmtEur(tarifs.total * 0.20)}</span></div>
            <div className="chiffrage-row total"><span className="k">Coût TTC</span><span className="v">{fmtEur(tarifs.total * 1.20)}</span></div>
          </div>
        </div>
      </div>
      <div className="card">
        <div className="card-title">Mes informations</div>
        <div className="card-body">
              <p style={{ fontSize: 12, color: "#78716c", margin: "0 0 12px" }}>
                « Soumettre » avertit l'architecte (dev@baracca.fr) que votre dossier est prêt à être
                examiné : le fichier JSON est alors enregistré et récupérable dans le back-office
                (compte client requis).
              </p>

              {session && session.loggedIn ? (
                <div className="account-box">
                  <div className="account-head">
                    <strong>Mes configurations</strong>
                    <span className="account-user" title="Login de connexion">
                      {session.user.email}
                    </span>
                  </div>
                  <div className="save-row">
                    <input
                      type="text"
                      placeholder={d.nom || "Nom de la configuration"}
                      value={saveName}
                      onChange={(e) => setSaveName(e.target.value)}
                    />
                    <button className="btn small" onClick={enregistrer}>Enregistrer cette configuration</button>
                  </div>
                  {configMsg && <div className="field-error" style={{ color: "#059669" }}>{configMsg}</div>}
                  {configs.length > 0 ? (
                    <ul className="config-list">
                      {configs.map((c) => (
                        <li key={c.id} className="config-row">
                          <div>
                            <strong>{c.nom}</strong>
                            <span className="config-date">modifiée le {c.updated}</span>
                          </div>
                          <div className="config-actions">
                            <button className="btn small" onClick={() => chargerConfig(c.id)}>Charger</button>
                            <button className="btn ghost small" onClick={() => supprimerConfig(c.id)}>Supprimer</button>
                          </div>
                        </li>
                      ))}
                    </ul>
                  ) : (
                    <p className="config-empty">Aucune configuration enregistrée pour le moment.</p>
                  )}
                  <p className="config-hint">
                    Retrouvez aussi vos configurations sur <a href="/compte.php" target="_blank">votre espace client</a>.
                  </p>
                </div>
              ) : session ? (
                <div className="account-box">
                  <strong>Enregistrement et soumission réservés aux comptes clients</strong>
                  <p className="config-hint">
                    <a href="/inscription.php" target="_blank">Créer un compte</a> ou{" "}
                    <a href="/connexion.php" target="_blank">se connecter</a> pour enregistrer
                    vos configurations et les soumettre à Baracca.
                  </p>
                </div>
              ) : null}

              {flash && <div className="field-error" style={{ marginBottom: 8, color: "#059669" }}>{flash}</div>}
              <div className="mesinfos-actions">
                <button
                  className="btn primary"
                  onClick={soumettre}
                  disabled={submitting || !session?.loggedIn}
                  title={session?.loggedIn ? "Avertir l'architecte (dev@baracca.fr) que ce dossier est prêt à être examiné" : "Connectez-vous pour soumettre"}
                >
                  {submitting ? "Envoi…" : "Soumettre"}
                </button>
              </div>
        </div>
      </div>
        </div>
      </div>

      <footer className="foot">
        Baracca × Archicad — données structurées pour l'API JSON Graphisoft + add-on Tapir.
      </footer>
    </div>
  );
}
