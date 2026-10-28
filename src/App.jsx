import React, { useEffect, useMemo, useState } from "react";

/* =================================================================
   Catalogue Baracca — à synchroniser avec votre catalogue produits
================================================================= */
const MURS = [
  { id: "clt90", nom: "Mur CLT 90 mm", epaisseur: 0.09, materiau: "CLT", rgb: [140, 107, 89] },
  { id: "clt140", nom: "Mur CLT 140 mm", epaisseur: 0.14, materiau: "CLT", rgb: [140, 107, 89] },
  { id: "ossature160", nom: "Ossature bois 160 mm", epaisseur: 0.16, materiau: "Ossature bois", rgb: [194, 169, 133] },
  { id: "bloc200", nom: "Bloc béton 200 mm", epaisseur: 0.20, materiau: "Béton", rgb: [158, 158, 158] },
];
const TOITURES = [
  { id: "monopente", nom: "Monopente" },
  { id: "deuxpentes", nom: "Deux pentes" },
  { id: "terrasse", nom: "Terrasse plate" },
];
const PLANCHERS = [
  { id: "clt_dalle", nom: "Dalle CLT + chape" },
  { id: "plancher_bois", nom: "Plancher bois sur lambourdes" },
  { id: "dalle_beton", nom: "Dalle béton" },
];
const FACADES = [
  { id: "bardage_bois", nom: "Bardage bois" },
  { id: "enduit", nom: "Enduit minéral" },
  { id: "tole", nom: "Tôle nervurée" },
  { id: "brique", nom: "Brique de parement" },
];
const FACES = [
  { key: "nord", label: "Mur Nord" },
  { key: "est", label: "Mur Est" },
  { key: "sud", label: "Mur Sud" },
  { key: "ouest", label: "Mur Ouest" },
];

const DEFAULTS = {
  nom: "Baracca_Module_01",
  longueur: 6.0,
  largeur: 3.0,
  hauteur: 2.8,
  murType: "clt140",
  toiture: "monopente",
  pente: 15,
  pignon: true,
  plancher: "clt_dalle",
  facade: "bardage_bois",
  murs: {
    nord: { actif: true, ouvertures: [] },
    est: { actif: true, ouvertures: [] },
    sud: { actif: true, ouvertures: [{ type: "baie", largeur: 1.2, hauteur: 2.15, position: 1.5 }] },
    ouest: { actif: true, ouvertures: [{ type: "porte", largeur: 0.9, hauteur: 2.05, position: 0.5 }] },
  },
};

/* URL de l'endpoint PHP de traçage (optionnel — laisser vide pour désactiver) */
const TRACE_URL = ""; // ex. "/api/trace_module.php"

/* Endpoints du site (même origine : l'application est servie par le site PHP
   sous /configurateur/ et intégrée par iframe dans configurateur.php) */
const API_SESSION = "/php/api_session.php";
const API_CONFIGS = "/php/api_configurations.php";
const API_SOUMETTRE = "/php/soumettre.php";

/* =================================================================
   Calculs
================================================================= */
const surfacePlancher = (d) => d.longueur * d.largeur;

function surfaceToiture(d) {
  if (d.toiture === "terrasse") return surfacePlancher(d);
  const angle = (d.pente * Math.PI) / 180;
  const base = d.toiture === "monopente" ? d.largeur : d.largeur / 2;
  return d.longueur * (base / Math.cos(angle));
}

function hauteurFaitage(d) {
  if (d.toiture === "terrasse") return 0;
  const angle = (d.pente * Math.PI) / 180;
  const base = d.toiture === "monopente" ? d.largeur : d.largeur / 2;
  return Math.tan(angle) * base;
}

const murLongueur = (d, face) =>
  face === "nord" || face === "sud" ? d.longueur : d.largeur;

const surfaceMenuiserie = (m) =>
  m.ouvertures.reduce((s, o) => s + o.largeur * o.hauteur, 0);

function validerOuverture(d, faceKey, o) {
  const L = murLongueur(d, faceKey);
  return o.position >= 0 && o.position + o.largeur <= L && o.hauteur <= d.hauteur;
}

/* =================================================================
   Export JSON (schéma v1.0 — consommé par baracca_import.py)
================================================================= */
function buildExport(d) {
  const murDef = MURS.find((m) => m.id === d.murType);
  const mursData = {};
  FACES.forEach((f) => {
    const face = d.murs[f.key];
    mursData[f.key] = {
      actif: face.actif,
      type: murDef.nom,
      epaisseur: murDef.epaisseur,
      materiau: murDef.materiau,
      longueur: murLongueur(d, f.key),
      hauteur: d.hauteur,
      facade: d.facade,
      ouvertures: face.ouvertures.map((o, i) => ({
        id: `${d.nom}_OUV_${f.key.toUpperCase()}_${i + 1}`,
        type: o.type,
        largeur: o.largeur,
        hauteur: o.hauteur,
        position: o.position,
      })),
    };
  });
  return {
    schemaVersion: "1.0",
    cible: "Archicad JSON API + Tapir",
    module: {
      nom: d.nom,
      dimensions: { longueur: d.longueur, largeur: d.largeur, hauteur: d.hauteur },
      plancher: PLANCHERS.find((p) => p.id === d.plancher).nom,
      toiture: {
        type: TOITURES.find((t) => t.id === d.toiture).nom,
        pente: d.toiture === "terrasse" ? null : d.pente,
        pignon: d.toiture === "deuxpentes" ? d.pignon : null,
        hauteurFaitage: +hauteurFaitage(d).toFixed(2),
        surface: +surfaceToiture(d).toFixed(2),
      },
      murs: mursData,
    },
    metriques: {
      surfacePlancher: +surfacePlancher(d).toFixed(2),
      surfaceToiture: +surfaceToiture(d).toFixed(2),
      surfaceMenuiserie: +FACES.reduce((s, f) => s + surfaceMenuiserie(d.murs[f.key]), 0).toFixed(2),
      volumeBrut: +(surfacePlancher(d) * d.hauteur).toFixed(2),
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
function Plan({ d, selectedFace, onSelectFace }) {
  const W = 560, H = 400, margin = 60;
  const scale = (Math.min(W, H) - 2 * margin) / Math.max(d.longueur, d.largeur);
  const L = d.longueur * scale, l = d.largeur * scale;
  const x0 = (W - L) / 2, y0 = (H - l) / 2;
  const murDef = MURS.find((m) => m.id === d.murType);
  const ep = Math.max(murDef.epaisseur * scale, 4);
  const facePath = {
    nord: { x: x0, y: y0, w: L, h: ep, horiz: true },
    sud: { x: x0, y: y0 + l - ep, w: L, h: ep, horiz: true },
    ouest: { x: x0, y: y0 + ep, w: ep, h: l - 2 * ep, horiz: false },
    est: { x: x0 + L - ep, y: y0 + ep, w: ep, h: l - 2 * ep, horiz: false },
  };
  const labelPos = {
    nord: [x0 + L / 2, y0 - 10],
    sud: [x0 + L / 2, y0 + l + 16],
    ouest: [x0 - 10, y0 + l / 2],
    est: [x0 + L + 10, y0 + l / 2],
  };
  return (
    <svg viewBox={`0 0 ${W} ${H}`} style={{ width: "100%", height: "auto" }}>
      <defs>
        <pattern id="hatch" width="6" height="6" patternUnits="userSpaceOnUse">
          <path d="M0 6 L6 0" stroke="#c8b8a5" strokeWidth="1" />
        </pattern>
      </defs>
      <rect x={x0} y={y0} width={L} height={l} fill="#faf7f2" />
      <text x={x0 + L / 2} y={y0 + l / 2 + (d.toiture === "deuxpentes" ? 18 : 4)} textAnchor="middle" fill="#a8a29e" fontSize="11">
        {d.longueur.toFixed(2)} m × {d.largeur.toFixed(2)} m — {surfacePlancher(d).toFixed(1)} m²
      </text>
      {FACES.map((f) => {
        const p = facePath[f.key];
        const isSel = selectedFace === f.key;
        const face = d.murs[f.key];
        const rgb = murDef.rgb.join(",");
        return (
          <g key={f.key} onClick={() => onSelectFace(f.key)} style={{ cursor: "pointer" }}>
            <rect
              x={p.x} y={p.y} width={p.w} height={p.h}
              fill={face.actif ? `rgb(${rgb})` : "#e5e5e5"}
              stroke={isSel ? "#b45309" : "#78716c"}
              strokeWidth={isSel ? 3 : 1}
            />
            {!face.actif && <rect x={p.x} y={p.y} width={p.w} height={p.h} fill="url(#hatch)" />}
            {face.actif && face.ouvertures.map((o, i) => {
              const ow = o.largeur * scale;
              const oh = Math.max(ep + 8, 14);
              const ox = p.horiz ? p.x + o.position * scale : p.x - 4;
              const oy = p.horiz ? p.y - 4 : p.y + o.position * scale;
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
      <line x1={x0} y1={y0 - 28} x2={x0 + L} y2={y0 - 28} stroke="#a8a29e" strokeDasharray="4 3" />
      <text x={x0 + L / 2} y={y0 - 32} textAnchor="middle" fill="#78716c" fontSize="10">{d.longueur.toFixed(2)} m</text>
      <line x1={x0 - 28} y1={y0} x2={x0 - 28} y2={y0 + l} stroke="#a8a29e" strokeDasharray="4 3" />
      <text x={x0 - 32} y={y0 + l / 2} textAnchor="middle" fill="#78716c" fontSize="10"
        transform={`rotate(-90 ${x0 - 32} ${y0 + l / 2})`}>{d.largeur.toFixed(2)} m</text>
      {d.toiture === "deuxpentes" && (
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
function Elevation({ d, faceKey }) {
  const W = 560, H = 320, margin = 60;
  const horiz = faceKey === "nord" || faceKey === "sud";
  const Lm = horiz ? d.longueur : d.largeur;
  const scale = (W - 2 * margin) / Math.max(Lm, 3);
  const hScale = (H - 2 * margin) / Math.max(d.hauteur + hauteurFaitage(d), 3);
  const L = Lm * scale;
  const hM = d.hauteur * hScale, hF = hauteurFaitage(d) * hScale;
  const x0 = (W - L) / 2, ySol = H - margin, yTop = ySol - hM;
  const face = d.murs[faceKey];
  const murDef = MURS.find((m) => m.id === d.murType);
  const nom = FACES.find((f) => f.key === faceKey).label;
  // Vue extérieure : miroir vu du NORD (regard vers le sud : l'ouest
  // apparaît à droite) et vu de l'EST (regard vers l'ouest : le sud
  // apparaît à droite, or les positions sont mesurées depuis le coin sud).
  // Pas de miroir pour sud et ouest.
  const miroir = faceKey === "nord" || faceKey === "est";
  const ox = (o) => x0 + (miroir ? L - (o.position + o.largeur) * scale : o.position * scale);
  let toitPath, toitFill = "none", toitDash = null, vueCote = false;
  if (d.toiture === "terrasse") {
    toitPath = `M ${x0} ${yTop} L ${x0 + L} ${yTop}`;
  } else if (d.toiture === "monopente") {
    if (horiz) {
      toitPath = `M ${x0} ${yTop} L ${x0 + L} ${yTop} M ${x0} ${yTop - hF} L ${x0 + L} ${yTop - hF}`;
      toitDash = "8 5"; vueCote = true;
    } else {
      // monopente (gouttière au sud, faitage au nord) :
      // vu de l'OUEST (nord à gauche) -> haut à gauche ;
      // vu de l'EST (nord à droite) -> haut à droite.
      toitPath = faceKey === "ouest"
        ? `M ${x0} ${yTop - hF} L ${x0 + L} ${yTop}`
        : `M ${x0} ${yTop} L ${x0 + L} ${yTop - hF}`;
    }
  } else if (horiz) {
    toitPath = `M ${x0} ${yTop} L ${x0 + L} ${yTop} M ${x0} ${yTop - hF} L ${x0 + L} ${yTop - hF}`;
    toitDash = "8 5"; vueCote = true;
  } else {
    toitPath = `M ${x0} ${yTop} L ${x0 + L / 2} ${yTop - hF} L ${x0 + L} ${yTop} Z`;
    toitFill = d.pignon ? "rgba(180,83,9,0.15)" : "none";
  }
  return (
    <svg viewBox={`0 0 ${W} ${H}`} style={{ width: "100%", height: "auto" }}>
      <line x1={20} y1={ySol} x2={W - 20} y2={ySol} stroke="#a8a29e" strokeWidth="2" />
      <rect x={x0} y={yTop} width={L} height={hM}
        fill={`rgb(${murDef.rgb.join(",")})`} opacity="0.5" stroke="#78716c" />
      {face.actif && face.ouvertures.map((o, i) => (
        <rect key={i} x={ox(o)} y={ySol - o.hauteur * hScale}
          width={o.largeur * scale} height={o.hauteur * hScale}
          fill={o.type === "porte" ? "#7c2d12" : "#0284c7"} opacity="0.85" rx="2" />
      ))}
      <path d={toitPath} stroke="#57534e" strokeWidth="6" fill={toitFill} strokeDasharray={toitDash} />
      {!face.actif && (
        <text x={x0 + L / 2} y={yTop + hM / 2} textAnchor="middle" fill="#a8a29e" fontSize="11">Mur désactivé</text>
      )}
      <text x={x0 + L / 2} y={yTop - hF - 12} textAnchor="middle" fill="#78716c" fontSize="11">
        {d.toiture === "terrasse"
          ? `${nom} — terrasse plate`
          : vueCote
            ? `${nom} — toiture vue de côté (faitage +${hauteurFaitage(d).toFixed(2)} m)`
            : `${nom} — ${d.pignon ? "pignon" : "croupe"}, faitage +${hauteurFaitage(d).toFixed(2)} m`}
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
  const [tab, setTab] = useState("geometrie");
  const [flash, setFlash] = useState("");

  /* ----- Espace client : session, configurations enregistrées ----- */
  const [session, setSession] = useState(null); // null = chargement en cours
  const [configs, setConfigs] = useState([]);
  const [saveName, setSaveName] = useState("");
  const [configMsg, setConfigMsg] = useState("");
  const [submitting, setSubmitting] = useState(false);

  const set = (patch) => setD((prev) => ({ ...prev, ...patch }));
  const setFace = (key, patch) =>
    setD((prev) => ({ ...prev, murs: { ...prev.murs, [key]: { ...prev.murs[key], ...patch } } }));

  const face = d.murs[selectedFace];
  const json = useMemo(() => JSON.stringify(buildExport(d), null, 2), [d]);

  const addOuverture = () =>
    setFace(selectedFace, {
      ouvertures: [...face.ouvertures, { type: "baie", largeur: 1.0, hauteur: 2.1, position: 1.0 }],
    });
  const updateOuverture = (i, patch) =>
    setFace(selectedFace, { ouvertures: face.ouvertures.map((o, j) => (j === i ? { ...o, ...patch } : o)) });
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
        if (!s.loggedIn) return;
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
        setTab("geometrie");
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

  const copier = async () => {
    try { await navigator.clipboard.writeText(json); notifier("JSON copié ✓"); }
    catch { notifier("Copie impossible"); }
  };

  const telecharger = async () => {
    const blob = new Blob([json], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `${(d.nom || "module_baracca").replace(/[^\w.-]/g, "_")}.json`;
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    URL.revokeObjectURL(url);
    notifier("Fichier téléchargé ✓");
    // Traçage optionnel vers le site (voir README — TRACE_URL)
    if (TRACE_URL) {
      try {
        await fetch(TRACE_URL, { method: "POST", headers: { "Content-Type": "application/json" }, body: json });
      } catch { /* silencieux : la traçabilité ne doit pas bloquer l'export */ }
    }
  };

  const tabs = [
    ["geometrie", "Géométrie"],
    ["enveloppe", "Enveloppe"],
    ["murs", "Murs & ouvertures"],
    ["export", "Export JSON"],
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

      <div className="grid2">
        <div className="card">
          <div className="card-title">Plan du module (cliquez un mur pour l'éditer)</div>
          <div className="card-body"><Plan d={d} selectedFace={selectedFace} onSelectFace={setSelectedFace} /></div>
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
            <Elevation d={d} faceKey={selectedFace} />
          </div>
        </div>
      </div>

      <div className="card" style={{ marginTop: 20 }}>
        <div className="card-body">
          <div className="tabs">
            {tabs.map(([key, label]) => (
              <button key={key} className={"tab-btn" + (tab === key ? " active" : "")}
                onClick={() => setTab(key)}>{label}</button>
            ))}
          </div>

          {tab === "geometrie" && (
            <div className="fields-grid">
              <Field label={`Nom du module`}>
                <input type="text" value={d.nom} onChange={(e) => set({ nom: e.target.value })} />
              </Field>
              <Field label={`Longueur : ${d.longueur.toFixed(2)} m`}>
                <input type="range" min="2" max="12" step="0.1" value={d.longueur}
                  onChange={(e) => set({ longueur: +e.target.value })} />
              </Field>
              <Field label={`Largeur : ${d.largeur.toFixed(2)} m`}>
                <input type="range" min="2" max="8" step="0.1" value={d.largeur}
                  onChange={(e) => set({ largeur: +e.target.value })} />
              </Field>
              <Field label={`Hauteur sous plafond : ${d.hauteur.toFixed(2)} m`}>
                <input type="range" min="2.2" max="4" step="0.05" value={d.hauteur}
                  onChange={(e) => set({ hauteur: +e.target.value })} />
              </Field>
              <Field label="Type de toiture">
                <select value={d.toiture} onChange={(e) => set({ toiture: e.target.value })}>
                  {TOITURES.map((t) => <option key={t.id} value={t.id}>{t.nom}</option>)}
                </select>
              </Field>
              {d.toiture === "deuxpentes" && (
                <label className="checkline">
                  <input type="checkbox" checked={d.pignon}
                    onChange={(e) => set({ pignon: e.target.checked })} />
                  Pignon sur les petits côtés (sinon croupe)
                </label>
              )}
              {d.toiture !== "terrasse" && (
                <Field label={`Pente : ${d.pente}° — faitage +${hauteurFaitage(d).toFixed(2)} m`}>
                  <input type="range" min="5" max="45" step="1" value={d.pente}
                    onChange={(e) => set({ pente: +e.target.value })} />
                </Field>
              )}
            </div>
          )}

          {tab === "enveloppe" && (
            <>
              <div className="fields-grid">
                <Field label="Type de mur porteur">
                  <select value={d.murType} onChange={(e) => set({ murType: e.target.value })}>
                    {MURS.map((m) => <option key={m.id} value={m.id}>{m.nom}</option>)}
                  </select>
                </Field>
                <Field label="Plancher">
                  <select value={d.plancher} onChange={(e) => set({ plancher: e.target.value })}>
                    {PLANCHERS.map((p) => <option key={p.id} value={p.id}>{p.nom}</option>)}
                  </select>
                </Field>
                <Field label="Façade (habillage extérieur)">
                  <select value={d.facade} onChange={(e) => set({ facade: e.target.value })}>
                    {FACADES.map((f) => <option key={f.id} value={f.id}>{f.nom}</option>)}
                  </select>
                </Field>
              </div>
              <div className="metrics">
                <div className="metric"><div className="k">Surface plancher</div><div className="v">{surfacePlancher(d).toFixed(1)} m²</div></div>
                <div className="metric"><div className="k">Surface toiture</div><div className="v">{surfaceToiture(d).toFixed(1)} m²</div></div>
                <div className="metric"><div className="k">Volume brut</div><div className="v">{(surfacePlancher(d) * d.hauteur).toFixed(1)} m³</div></div>
                <div className="metric"><div className="k">Menuiseries</div><div className="v">{FACES.reduce((s, f) => s + surfaceMenuiserie(d.murs[f.key]), 0).toFixed(2)} m²</div></div>
              </div>
            </>
          )}

          {tab === "murs" && (
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
                Mur {FACES.find((f) => f.key === selectedFace).label.toLowerCase()} présent dans le module
              </label>
              <hr className="sep" />
              {face.actif ? (
                <div>
                  {face.ouvertures.map((o, i) => (
                    <div key={i} className="ouverture">
                      <div className="ouverture-head">
                        <span className="chip">Ouverture {i + 1} — mur {selectedFace}</span>
                        <button className="btn ghost small" onClick={() => removeOuverture(i)}>Supprimer</button>
                      </div>
                      <div className="fields-grid">
                        <Field label="Type">
                          <select value={o.type} onChange={(e) => updateOuverture(i, { type: e.target.value })}>
                            <option value="baie">Baie vitrée</option>
                            <option value="fenetre">Fenêtre</option>
                            <option value="porte">Porte</option>
                          </select>
                        </Field>
                        <Field label="Largeur (m)">
                          <input type="number" step="0.1" value={o.largeur}
                            onChange={(e) => updateOuverture(i, { largeur: +e.target.value })} />
                        </Field>
                        <Field label="Hauteur (m)">
                          <input type="number" step="0.05" value={o.hauteur}
                            onChange={(e) => updateOuverture(i, { hauteur: +e.target.value })} />
                        </Field>
                        <Field label="Position depuis l'origine (m)">
                          <input type="number" step="0.1" value={o.position}
                            onChange={(e) => updateOuverture(i, { position: +e.target.value })} />
                        </Field>
                      </div>
                      {!validerOuverture(d, selectedFace, o) && (
                        <div className="field-error">
                          L'ouverture dépasse le mur ({murLongueur(d, selectedFace).toFixed(2)} m) ou la hauteur ({d.hauteur.toFixed(2)} m).
                        </div>
                      )}
                    </div>
                  ))}
                  <button className="btn small" onClick={addOuverture}>+ Ajouter une ouverture</button>
                </div>
              ) : (
                <p style={{ fontSize: "0.85rem", color: "#78716c" }}>
                  Mur désactivé — aucun élément ne sera généré pour cette face.
                </p>
              )}
            </>
          )}

          {tab === "export" && (
            <>
              <div className="export-toolbar">
                <p>
                  Ce fichier JSON est le format d'échange : le script Python côté Archicad
                  (API JSON officielle + Tapir) le lit et génère murs, toiture, plancher et ouvertures.
                </p>
                <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
                  <button className="btn small" onClick={copier}>Copier le JSON</button>
                  <button className="btn primary small" onClick={telecharger}>Télécharger module.json</button>
                  <button
                    className="btn primary small"
                    onClick={soumettre}
                    disabled={submitting || !session?.loggedIn}
                    title={session?.loggedIn ? "Avertir l'architecte (dev@baracca.fr) que ce dossier est prêt à être examiné" : "Connectez-vous pour soumettre"}
                  >
                    {submitting ? "Envoi…" : "Soumettre"}
                  </button>
                </div>
              </div>
              <p style={{ fontSize: 12, color: "#78716c", margin: "0 0 12px" }}>
                « Soumettre » avertit l'architecte (dev@baracca.fr) que votre dossier est prêt à être
                examiné : le fichier JSON est alors enregistré et récupérable dans le back-office
                (compte client requis).
              </p>

              {session && session.loggedIn ? (
                <div className="account-box">
                  <div className="account-head">
                    <strong>Mes configurations</strong>
                    <span className="account-user">
                      {session.user.prenom} {session.user.nom} — {session.user.email}
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
              <pre className="json">{json}</pre>
            </>
          )}
        </div>
      </div>

      <footer className="foot">
        Baracca × Archicad — données structurées pour l'API JSON Graphisoft + add-on Tapir.
      </footer>
    </div>
  );
}
