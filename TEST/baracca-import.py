#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# BARACCA-IMPORT v2.3 — vérifiez la présence de cette ligne : elle prouve
# que vous exécutez bien la dernière version du script.
"""
Baracca — Import de module configuré depuis le site web vers Archicad
=====================================================================
Brique 2 du flux : lit le JSON exporté par le configurateur web Baracca
et génère les éléments dans le projet Archicad ouvert.

Schémas Tapir v1.7.2 (vérifiés contre les exemples officiels du dépôt
ENZYME-APD/tapir-archicad-automation, fichier create_elements.py) :

  CreateLayers  : {"layerDataArray": [{"name": ...}]}
  CreateWalls   : {"wallsData": [{"begCoordinate": {x,y},
                                  "endCoordinate": {x,y},
                                  "height": h, "thickness": t,
                                  "favoriteName": "..." (optionnel)}]}
      -> réponse : {"elements": [{"elementId": {...}}, ...]}
  CreateWindows : {"windowsData": [{"ownerWallId": <elementId>,
                                    "centerOffset": d (centre, depuis
                                    begCoordinate du mur),
                                    "width": w, "height": h,
                                    "favoriteName": ... (optionnel)}]}
  CreateDoors   : {"doorsData": [mêmes champs que CreateWindows]}
  CreateSlabs   : {"slabsData": [{"level": z,
                                  "polygonCoordinates": [{x,y}...]}]}
  CreateRoofs   : {"roofsData": [{"level": z,
                                  "polygonCoordinates": [{x,y}...},
                                  "pivotLine": {begin,end} + "angle":
                                  pour une toiture monopente]}

Prérequis :
  1. Archicad lancé, projet/gabarit Baracca ouvert.
  2. Add-on Tapir installé (Options > Gestionnaire d'add-ons).
  3. pip install archicad

Utilisation :
  python baracca_import.py module.json          # à l'origine (0,0)
  python baracca_import.py module.json --pick   # origine cliquée
  python baracca_import.py --probe              # diagnostic des commandes

Méthode : les murs sont créés à partir de FAVORIS du gabarit Baracca
(un favori de l'outil Mur par type de mur) : le script n'envoie que la
géométrie, Archicad applique composite, calque et attributs du favori.
"""

import argparse
import json
import math
import sys

from archicad import ACConnection

# ---------------------------------------------------------------------------
# Favoris du gabarit Baracca (Fichier > Systèmes d'environnement > Favoris).
# Créez un favori de l'outil Mur par type Baracca avec le bon composite et
# calque, et reportez son nom exact ici. Laissez None pour utiliser les
# réglages par défaut de l'outil.
# ---------------------------------------------------------------------------
FAVORIS_MURS = {
    "Mur CLT 90 mm": None,           # ex. "Baracca CLT 90"
    "Mur CLT 140 mm": None,          # ex. "Baracca CLT 140"
    "Ossature bois 160 mm": None,    # ex. "Baracca OSS 160"
    "Bloc béton 200 mm": None,       # ex. "Baracca BET 200"
}
FAVORIS_PLANCHER = {
    "Dalle CLT + chape": None,
    "Plancher bois sur lambourdes": None,
    "Dalle béton": None,
}
FAVORIS_OUVERTURES = {
    "baie": None,
    "fenetre": None,
    "porte": None,
}

LAYER = "BARACCA_Modules"


class ArchicadBridge:
    """Surcouche au-dessus de l'API JSON + Tapir, avec diagnostic."""

    def __init__(self):
        self.conn = ACConnection.connect()
        if self.conn is None:
            sys.exit("Aucune instance d'Archicad détectée. "
                     "Lancez Archicad avec un projet ouvert.")
        self.commands = self.conn.commands
        self.types = self.conn.types
        self._check_tapir()

    def _check_tapir(self):
        try:
            version = self.tapir("GetAddOnVersion")
            print(f"Tapir détecté : v{version.get('version', '?')}")
        except Exception as exc:
            sys.exit(f"Add-on Tapir introuvable : {exc}")

    def tapir(self, command, parameters=None):
        """Exécute une commande Tapir ; sur erreur, affiche payload et
        message exact pour un diagnostic immédiat."""
        try:
            return self.commands.ExecuteAddOnCommand(
                self.types.AddOnCommandId("TapirCommand", command),
                parameters or {},
            )
        except Exception as exc:
            print(f"\n[ÉCHEC Tapir] {command}")
            print(f"  payload : {json.dumps(parameters, default=str)[:800]}")
            print(f"  erreur  : {exc}")
            raise

    def ensure_layer(self, name):
        """Crée le calque ; 'Already exists' (créé par un import
        précédent) est traité comme un succès."""
        response = self.tapir("CreateLayers",
                              {"layerDataArray": [{"name": name}]})
        for entry in response.get("attributeIds", []):
            err = entry.get("error", {})
            if err and "Already exists" not in err.get("message", ""):
                print(f"  ! Calque {name} : {err}")

    def pick_point(self):
        return self.tapir("GetPointFromUser")["point"]


# ---------------------------------------------------------------------------
# Mode --probe : valide chaque commande avec une charge minimale
# ---------------------------------------------------------------------------
def probe():
    bridge = ArchicadBridge()
    print("=" * 64)
    print("MODE PROBE — validation des commandes (schémas Tapir 1.7.2)")
    print("=" * 64)

    ok = []

    # 1. Calque (« Already exists » = déjà créé par un import précédent : OK)
    try:
        r = bridge.tapir("CreateLayers",
                         {"layerDataArray": [{"name": LAYER}]})
        deja = any("Already exists" in str(e) for e in r.get("attributeIds", []))
        print(f"CreateLayers           OK : "
              f"{'(calque déjà existant)' if deja else json.dumps(r, default=str)[:120]}")
        ok.append(True)
    except Exception:
        ok.append(False)

    # 2. Mur (ligne directrice sur le contour : corps du mur vers
    #    l'intérieur avec le gabarit Baracca)
    wall_id = None
    try:
        payload = {
            "begCoordinate": {"x": 0.0, "y": 0.0},
            "endCoordinate": {"x": 3.0, "y": 0.0},
            "height": 2.8,
            "thickness": 0.14,
        }
        r = bridge.tapir("CreateWalls", {"wallsData": [payload]})
        print(f"CreateWalls            OK : {json.dumps(r, default=str)[:200]}")
        wall_id = r["elements"][0]["elementId"]
        ok.append(True)
    except Exception:
        ok.append(False)

    # 3. Plancher (RDC : niveau 0)
    try:
        r = bridge.tapir("CreateSlabs", {"slabsData": [{
            "level": 0.0,
            "polygonCoordinates": [
                {"x": 0.0, "y": 0.0}, {"x": 3.0, "y": 0.0},
                {"x": 3.0, "y": 2.0}, {"x": 0.0, "y": 2.0},
            ],
        }]})
        print(f"CreateSlabs            OK : {json.dumps(r, default=str)[:160]}")
        ok.append(True)
    except Exception:
        ok.append(False)

    # 4. Toiture monopente (pivotLine + angle en radians)
    try:
        r = bridge.tapir("CreateRoofs", {"roofsData": [{
            "level": 2.8,
            "polygonCoordinates": [
                {"x": 0.0, "y": 0.0}, {"x": 3.0, "y": 0.0},
                {"x": 3.0, "y": 2.0}, {"x": 0.0, "y": 2.0},
            ],
            "pivotLine": {
                "begCoordinate": {"x": 0.0, "y": 0.0},
                "endCoordinate": {"x": 3.0, "y": 0.0},
            },
            # 15° exprimés en radians (convention API Archicad)
            "angle": round(math.radians(15), 6),
        }]})
        errs = [e.get("error") for e in r.get("elements", [])
                if e.get("error")]
        if errs:
            print(f"CreateRoofs (monopente) ERREUR PAR ÉLÉMENT : {errs}")
            ok.append(False)
        else:
            print(f"CreateRoofs (monopente) OK : "
                  f"{json.dumps(r, default=str)[:160]}")
            # Lecture retour : angle réellement stocké par Archicad
            roof_id = r["elements"][0]["elementId"]
            try:
                details = bridge.tapir("GetDetailsOfElements",
                                       {"elements": [roof_id]})
                print("  Lecture retour (GetDetailsOfElements) :")
                print(f"  {json.dumps(details, default=str)[:600]}")
                print("  -> vérifiez dans Archicad que la pente affichée"
                      " est ~15°")
            except Exception as exc:
                print(f"  (lecture retour impossible : {exc})")
            ok.append(True)
    except Exception:
        ok.append(False)

    # 5. Fenêtre + porte dans le mur de test
    if wall_id is not None:
        try:
            r = bridge.tapir("CreateWindows", {"windowsData": [{
                "ownerWallId": wall_id,
                "centerOffset": 1.0,
                "width": 1.0,
                "height": 1.2,
            }]})
            print(f"CreateWindows          OK : "
                  f"{json.dumps(r, default=str)[:160]}")
            ok.append(True)
        except Exception:
            ok.append(False)
        try:
            r = bridge.tapir("CreateDoors", {"doorsData": [{
                "ownerWallId": wall_id,
                "centerOffset": 2.2,
                "width": 0.9,
                "height": 2.1,
            }]})
            print(f"CreateDoors            OK : "
                  f"{json.dumps(r, default=str)[:160]}")
            ok.append(True)
        except Exception:
            ok.append(False)

    print("-" * 64)
    if all(ok):
        print("TOUT EST VALIDE ✓ — les éléments de test peuvent être "
              "supprimés (Ctrl+Z) et l'import peut être lancé.")
    else:
        print(f"{ok.count(False)} commande(s) en échec — envoyez la sortie "
              "complète pour ajustement.")


# ---------------------------------------------------------------------------
# Géométrie du module
# Origine au coin Sud-Ouest ; X vers l'Est, Y vers le Nord.
# begCoordinate de chaque mur = point depuis lequel le configurateur mesure
# la position des ouvertures.
# ---------------------------------------------------------------------------
def wall_segments(module):
    """Axes des murs pour la création dans Archicad.

    Origine au coin Sud-Ouest ; X vers l'Est, Y vers le Nord ; le contour
    du module = face EXTÉRIEURE des murs.

    Lignes directrices tracées sur le contour : avec le gabarit Baracca
    (réglage de l'outil Mur), le corps du mur se place vers l'INTÉRIEUR
    depuis sa ligne directrice — MAIS du côté déterminé par le SENS de
    tracé de la ligne (validé en réel) :
      sud  : Ouest -> Est    (corps vers l'intérieur ✓)
      est  : Sud  -> Nord    (corps vers l'intérieur ✓)
      nord : Est  -> Ouest   (sens inversé, sinon corps vers l'extérieur)
      ouest: Nord -> Sud     (sens inversé, sinon corps vers l'extérieur)

    Convention du configurateur pour les ouvertures (voir le plan) :
      - sud et nord : position mesurée depuis le coin OUEST ;
      - est et ouest : position mesurée depuis le coin NORD.
    """
    dims = module["dimensions"]
    L, l, H = dims["longueur"], dims["largeur"], dims["hauteur"]
    axes = {
        "sud":   ((0.0, 0.0), (L, 0.0)),
        "est":   ((L, 0.0),   (L, l)),
        "nord":  ((L, l),     (0.0, l)),   # Est -> Ouest
        "ouest": ((0.0, l),   (0.0, 0.0)),  # Nord -> Sud
    }
    segments = {}
    for face_key in ("sud", "est", "nord", "ouest"):
        face = module["murs"][face_key]
        if not face["actif"]:
            continue
        beg, end = axes[face_key]
        segments[face_key] = {
            "beg": beg, "end": end,
            "hauteur": H,
            "epaisseur": face["epaisseur"],
            "type": face["type"],
            "ouvertures": face.get("ouvertures", []),
        }
    return segments


def slab_polygon(module):
    dims = module["dimensions"]
    L, l = dims["longueur"], dims["largeur"]
    return [{"x": 0.0, "y": 0.0}, {"x": L, "y": 0.0},
            {"x": L, "y": l}, {"x": 0.0, "y": l}]


# ---------------------------------------------------------------------------
# Import principal
# ---------------------------------------------------------------------------
def import_module(data, pick_origin=False):
    module = data["module"]
    name = module["nom"]
    print(f"Import du module « {name} » (schéma {data.get('schemaVersion')})…")

    bridge = ArchicadBridge()
    bridge.ensure_layer(LAYER)

    ox, oy = 0.0, 0.0
    if pick_origin:
        print("Cliquez l'origine du module dans la fenêtre Archicad…")
        point = bridge.pick_point()
        ox, oy = point["x"], point["y"]
        print(f"Origine retenue : ({ox:.2f}, {oy:.2f})")

    def tr(pt):
        return {"x": pt[0] + ox, "y": pt[1] + oy}

    # --- 1. Murs ----------------------------------------------------------
    # Lignes directrices tracées sur le contour : avec le gabarit Baracca,
    # le corps du mur se place vers l'intérieur depuis sa ligne directrice
    # (validé en réel). AUCUN décalage de demi-épaisseur à appliquer.
    segments = wall_segments(module)
    walls_data = []
    for face_key in ("sud", "est", "nord", "ouest"):
        seg = segments.get(face_key)
        if not seg:
            continue
        item = {
            "begCoordinate": tr(seg["beg"]),
            "endCoordinate": tr(seg["end"]),
            "height": seg["hauteur"],
            "thickness": seg["epaisseur"],
        }
        favori = FAVORIS_MURS.get(seg["type"])
        if favori:
            item["favoriteName"] = favori
        elif face_key == "sud":
            print(f"  ! Pas de favori pour « {seg['type']} » : réglages par "
                  f"défaut de l'outil Mur (renseignez FAVORIS_MURS).")
        walls_data.append((face_key, item))

    print(f"Création de {len(walls_data)} mur(s)…")
    response = bridge.tapir("CreateWalls",
                            {"wallsData": [it for _, it in walls_data]})
    elements = response.get("elements", [])
    wall_ids = {}
    for (face_key, _), el in zip(walls_data, elements):
        wall_ids[face_key] = el["elementId"]

    # --- 2. Plancher -------------------------------------------------------
    dims = module["dimensions"]
    slab_item = {
        "level": 0.0,   # RDC : étage d'implantation 0, altitude 0
        "polygonCoordinates": [{"x": p["x"] + ox, "y": p["y"] + oy}
                               for p in slab_polygon(module)],
    }
    favori_pl = FAVORIS_PLANCHER.get(module["plancher"])
    if favori_pl:
        slab_item["favoriteName"] = favori_pl
    print(f"Création du plancher ({module['plancher']})…")
    slab_response = bridge.tapir("CreateSlabs", {"slabsData": [slab_item]})
    errs = [e.get("error") for e in slab_response.get("elements", [])
            if e.get("error")]
    if errs:
        print(f"  ! Plancher non créé : {errs}")

    # --- 3. Toiture ---------------------------------------------------------
    toit = module["toiture"]
    H = dims["hauteur"]
    L, l = dims["longueur"], dims["largeur"]
    angle = round(math.radians(toit.get("pente") or 0), 6)
    empreinte = [{"x": ox, "y": oy}, {"x": L + ox, "y": oy},
                 {"x": L + ox, "y": l + oy}, {"x": ox, "y": l + oy}]

    print(f"Création de la toiture ({toit['type']}"
          f"{', pignon' if toit.get('pignon') else ''})…")

    if toit["type"] == "Monopente":
        # Pivot = gouttière sud ; angle en RADIANS
        roofs_data = [{
            "level": H,
            "polygonCoordinates": empreinte,
            "pivotLine": {
                "begCoordinate": {"x": ox, "y": oy},
                "endCoordinate": {"x": L + ox, "y": oy},
            },
            "angle": angle,
        }]
    elif toit["type"] == "Deux pentes" and toit.get("pignon"):
        # Pignon : deux versants monoplanes qui se rejoignent au
        # faitage (dans l'axe de la longueur) ; pignons verticaux
        # aux extrémités (côtés courts).
        demi = l / 2.0
        roofs_data = [
            {   # versant sud : pivot sur la gouttière sud
                "level": H,
                "polygonCoordinates": [
                    {"x": ox, "y": oy}, {"x": L + ox, "y": oy},
                    {"x": L + ox, "y": demi + oy},
                    {"x": ox, "y": demi + oy},
                ],
                "pivotLine": {
                    "begCoordinate": {"x": ox, "y": oy},
                    "endCoordinate": {"x": L + ox, "y": oy},
                },
                "angle": angle,
            },
            {   # versant nord : pivot sur la gouttière nord.
                # Pivot orienté Est->Ouest : le versant lève vers le sud
                # (l'angle positif lève toujours à gauche de la direction
                # beg->end du pivot).
                "level": H,
                "polygonCoordinates": [
                    {"x": ox, "y": demi + oy},
                    {"x": L + ox, "y": demi + oy},
                    {"x": L + ox, "y": l + oy}, {"x": ox, "y": l + oy},
                ],
                "pivotLine": {
                    "begCoordinate": {"x": L + ox, "y": l + oy},
                    "endCoordinate": {"x": ox, "y": l + oy},
                },
                "angle": angle,
            },
        ]
    else:
        # Deux pentes SANS pignon (croupe) : toiture multi-plans
        # Tapir sur l'emprise. Terrasse : idem, à plat.
        roofs_data = [{"level": H, "polygonCoordinates": empreinte}]
        if toit["type"] == "Deux pentes":
            roofs_data[0]["angle"] = angle

    try:
        roof_response = bridge.tapir("CreateRoofs",
                                     {"roofsData": roofs_data})
    except Exception:
        if toit["type"] == "Deux pentes" and not toit.get("pignon"):
            # Repli : cette version de Tapir n'accepte pas 'angle' en
            # multi-plans -> pente par défaut de l'outil Toiture.
            roofs_data[0].pop("angle", None)
            print("  ! Champ 'angle' refusé en multi-plans : toiture "
                  "créée avec la pente par défaut de l'outil.")
            roof_response = bridge.tapir("CreateRoofs",
                                         {"roofsData": roofs_data})
        else:
            raise
    errs = [e.get("error") for e in roof_response.get("elements", [])
            if e.get("error")]
    if errs:
        print(f"  ! Toiture non créée : {errs}")
    else:
        print(f"  Toiture créée ({len(roofs_data)} élément(s)).")

    # --- 4. Ouvertures -------------------------------------------------------
    n_ok, n_err = 0, 0
    for face_key in ("sud", "est", "nord", "ouest"):
        seg = segments.get(face_key)
        wall_id = wall_ids.get(face_key)
        if not seg or wall_id is None:
            continue
        for ouverture in seg["ouvertures"]:
            favori_ouv = FAVORIS_OUVERTURES.get(ouverture["type"])
            # centerOffset = distance du CENTRE de l'ouverture depuis le
            # begCoordinate du mur (sens de tracé cf. wall_segments).
            # Positions du configurateur : sud/nord depuis le coin OUEST,
            # est/ouest depuis le coin NORD.
            if face_key == "sud":        # beg au coin SW, tracé vers l'Est
                centre = ouverture["position"] + ouverture["largeur"] / 2.0
            elif face_key == "nord":     # beg au coin SE, tracé vers l'Ouest
                centre = dims["longueur"] - ouverture["position"] \
                         - ouverture["largeur"] / 2.0
            elif face_key == "est":      # beg au coin SE, tracé vers le Nord
                centre = dims["largeur"] - ouverture["position"] \
                         - ouverture["largeur"] / 2.0
            else:                        # ouest : beg au coin NW, vers le Sud
                centre = ouverture["position"] + ouverture["largeur"] / 2.0
            item = {
                "ownerWallId": wall_id,
                "centerOffset": centre,
                "width": ouverture["largeur"],
                "height": ouverture["hauteur"],
            }
            if favori_ouv:
                item["favoriteName"] = favori_ouv
            command = ("CreateDoors" if ouverture["type"] == "porte"
                       else "CreateWindows")
            key = "doorsData" if ouverture["type"] == "porte" \
                else "windowsData"
            try:
                bridge.tapir(command, {key: [item]})
                n_ok += 1
            except Exception:
                n_err += 1
                print(f"  ! Ouverture {ouverture.get('id', '?')} non placée "
                      f"sur le mur {face_key}")

    # --- 5. Zoom -------------------------------------------------------------
    if wall_ids:
        try:
            bridge.tapir("FitInWindow",
                         {"elements": list(wall_ids.values())})
        except Exception:
            pass

    print("-" * 60)
    print(f"Module « {name} » importé :")
    print(f"  - {len(walls_data)} murs, 1 plancher, 1 toiture")
    print(f"  - {n_ok} ouverture(s) placée(s), {n_err} échec(s)")
    print("Terminé. Vérifiez le résultat dans la fenêtre 3D.")


def main():
    parser = argparse.ArgumentParser(
        description="Importe un module Baracca (JSON du configurateur web) "
                    "dans le projet Archicad ouvert.")
    print("BARACCA-IMPORT v2.3 — murs: contour, sens de tracé intérieur ; "
          "plancher: RDC altitude 0 ; pignon: 2 versants")
    parser.add_argument("fichier", nargs="?", help="Chemin du JSON du module")
    parser.add_argument("--pick", action="store_true",
                        help="Choisir l'origine du module en cliquant")
    parser.add_argument("--probe", action="store_true",
                        help="Valider les commandes Tapir avec des éléments "
                             "de test (supprimables via Ctrl+Z)")
    args = parser.parse_args()

    if args.probe:
        probe()
        return
    if not args.fichier:
        parser.error("Fichier JSON requis (ou --probe)")

    with open(args.fichier, "r", encoding="utf-8") as fh:
        data = json.load(fh)
    if "module" not in data:
        sys.exit("JSON invalide : clé 'module' absente.")
    import_module(data, pick_origin=args.pick)


if __name__ == "__main__":
    main()