import fs from "node:fs/promises";
import { Presentation, PresentationFile } from "@oai/artifact-tool";

const OUT = "C:/Users/leejo/Desktop/cuidado_adulto_mayor/output/presentaciones/Calculo_Costos_Diseno_Implementacion_SW.pptx";
const RENDER = "C:/Users/leejo/Desktop/cuidado_adulto_mayor/tmp/costos_deck/render";
const p = Presentation.create({ slideSize: { width: 1280, height: 720 } });

const C = {
  red: "#c71920", darkRed: "#721014", pale: "#fff4f4",
  ink: "#111111", gray: "#666666", light: "#f7f7f7", white: "#ffffff",
};

function box(slide, name, x, y, w, h, fill = "none", line = "none", width = 0) {
  return slide.shapes.add({
    geometry: "rect", name, position: { left: x, top: y, width: w, height: h },
    fill, line: { style: "solid", fill: line, width },
  });
}

function text(slide, name, value, x, y, w, h, size = 28, opts = {}) {
  const s = slide.shapes.add({
    geometry: "textbox", name, position: { left: x, top: y, width: w, height: h },
    fill: "none", line: { style: "solid", fill: "none", width: 0 },
  });
  s.text = value;
  s.text.style = {
    fontSize: size, fontFamily: "Arial", color: opts.color || C.ink,
    bold: !!opts.bold, alignment: opts.align || "left",
    verticalAlignment: opts.valign || "middle",
  };
  return s;
}

function base(title, num) {
  const s = p.slides.add();
  s.background.fill = C.white;
  box(s, "right-sidebar", 1152, 0, 128, 720, C.darkRed);
  box(s, "sidebar-accent", 1152, 0, 10, 720, "#941319");
  text(s, "title", title, 18, 28, 1100, 68, 42, { bold: true, color: C.red });
  box(s, "title-rule-shadow", 12, 111, 1120, 5, "#f1caca");
  box(s, "title-rule", 12, 110, 1120, 3, C.ink);
  text(s, "course-footer", "Planificación y Estimación · Organízate", 160, 675, 700, 28, 20, { color: C.ink });
  text(s, "page-number", String(num), 932, 675, 80, 28, 20, { align: "center" });
  text(s, "side-page-number", `${num} de 6`, 1170, 675, 88, 28, 16, { color: C.white, align: "center" });
  s.speakerNotes.textFrame.setText(
    "[Sources]\n- Proyecto local: README.md, docker-compose.yml, frontend/package.json y backend/composer.json.\n- Referencia visual proporcionada por el usuario."
  );
  return s;
}

function bullet(slide, value, y, size = 26, h = 60) {
  text(slide, `bullet-mark-${y}`, "⊙", 18, y, 38, 42, size + 4, { color: C.red, bold: true });
  text(slide, `bullet-text-${y}`, value, 58, y - 2, 1040, h, size);
}

// 1
{
  const s = base("Cálculo de Costos del Software", 1);
  text(s, "deck-subtitle", "Diseño e implementación de “Organízate”", 58, 180, 980, 90, 38, { bold: true });
  bullet(s, "El presupuesto debe cubrir todo el ciclo de vida, no solamente la programación.", 300, 30, 88);
  bullet(s, "La estimación parte del alcance, las horas de trabajo, las tarifas y los riesgos.", 420, 30, 88);
  box(s, "opening-callout", 60, 550, 960, 74, C.pale, C.red, 2);
  text(s, "opening-formula", "Costo total = Diseño + Implementación + Pruebas + Despliegue + Contingencia", 82, 562, 920, 48, 25, { bold: true, align: "center" });
}

// 2
{
  const s = base("Distribución del Esfuerzo", 2);
  bullet(s, "Una distribución de referencia reparte el esfuerzo total entre las principales actividades:", 150, 27, 70);
  const t = s.tables.add({
    rows: 6, columns: 2, left: 42, top: 260, width: 1070, height: 330,
    columnWidths: [760, 310],
    values: [
      ["Tipo de actividad", "Porcentaje"],
      ["Análisis", "10 %"],
      ["Diseño", "20 %"],
      ["Implementación", "40 %"],
      ["Pruebas", "15 %"],
      ["Sobrecarga (otras actividades)", "15 %"],
    ],
  });
  t.borders.assign({ style: "solid", fill: C.ink, width: 1.5 });
  t.cells.block({ row: 0, column: 0, rowCount: 1, columnCount: 2 }).assign({
    fill: C.light, textStyle: { fontSize: 25, bold: true, color: C.ink },
    anchor: "middle", margins: { left: 14, right: 14, top: 8, bottom: 8 },
  });
  t.cells.block({ row: 1, column: 0, rowCount: 5, columnCount: 2 }).assign({
    textStyle: { fontSize: 23, color: C.ink },
    anchor: "middle", margins: { left: 14, right: 14, top: 6, bottom: 6 },
  });
  for (let r = 0; r < 6; r++) t.getCell(r, 1).text.style = { fontSize: r === 0 ? 25 : 23, bold: r === 0, alignment: "center", color: C.ink };
  t.cells.block({ row: 3, column: 0, rowCount: 1, columnCount: 2 }).assign({
    fill: C.pale, textStyle: { fontSize: 24, bold: true, color: C.red },
  });
}

// 3
{
  const s = base("Costos del Diseño", 3);
  bullet(s, "El diseño transforma los requisitos en una solución técnica y visual antes de programar.", 145, 27, 70);
  const rows = [
    ["Actividad", "Entregable que se estima"],
    ["Análisis de requisitos", "Alcance, historias de usuario y criterios de aceptación"],
    ["Arquitectura", "Frontend, backend, API, seguridad e infraestructura"],
    ["Base de datos", "Entidades, relaciones, migraciones e integridad"],
    ["UX/UI", "Flujos, prototipos, navegación por roles y accesibilidad"],
    ["Documentación", "Diagramas, especificaciones y decisiones técnicas"],
  ];
  const t = s.tables.add({ rows: 6, columns: 2, left: 42, top: 255, width: 1070, height: 340, columnWidths: [345, 725], values: rows });
  t.borders.assign({ style: "solid", fill: C.ink, width: 1.2 });
  t.cells.block({ row: 0, column: 0, rowCount: 1, columnCount: 2 }).assign({ fill: C.light, textStyle: { fontSize: 23, bold: true }, anchor: "middle" });
  t.cells.block({ row: 1, column: 0, rowCount: 5, columnCount: 2 }).assign({ textStyle: { fontSize: 20 }, anchor: "middle", margins: { left: 12, right: 12, top: 5, bottom: 5 } });
  text(s, "design-formula", "Costo de diseño = Σ (horas por actividad × tarifa del perfil)", 190, 610, 770, 42, 22, { bold: true, color: C.red, align: "center" });
}

// 4
{
  const s = base("Costos de Implementación", 4);
  bullet(s, "La implementación concentra el mayor esfuerzo porque convierte el diseño en funciones operativas.", 145, 27, 74);
  const items = [
    ["Frontend", "HTML, CSS, JavaScript, interfaces y consumo de la API"],
    ["Backend", "PHP/Laravel, reglas de negocio, modelos y controladores"],
    ["Datos", "PostgreSQL, migraciones, consultas, integridad y respaldos"],
    ["Integración", "Autenticación, Laravel Sanctum y permisos por roles"],
    ["Infraestructura", "Docker, Docker Compose, servidor y configuración"],
    ["Calidad", "Pruebas, corrección de errores, revisión y retrabajo"],
  ];
  let y = 250;
  for (const [head, body] of items) {
    text(s, `impl-head-${head}`, head, 58, y, 235, 48, 23, { bold: true, color: C.red });
    text(s, `impl-body-${head}`, body, 300, y, 790, 48, 21);
    box(s, `impl-rule-${head}`, 58, y + 48, 1030, 1, "#b9b9b9");
    y += 59;
  }
}

// 5
{
  const s = base("Costos Relacionados y Riesgos", 5);
  bullet(s, "El presupuesto final incorpora recursos que suelen quedar fuera de la estimación inicial.", 145, 27, 70);
  const t = s.tables.add({
    rows: 6, columns: 2, left: 42, top: 252, width: 1070, height: 342, columnWidths: [330, 740],
    values: [
      ["Grupo", "Conceptos incluidos"],
      ["Personal", "Analista, diseño UX/UI, desarrollo, QA, DevOps y gestión"],
      ["Infraestructura", "Equipos, servidor, dominio, respaldos, internet y energía"],
      ["Despliegue", "Configuración, datos iniciales, manuales y capacitación"],
      ["Mantenimiento", "Soporte correctivo, adaptativo, evolutivo y actualizaciones"],
      ["Riesgos", "Cambios de alcance, retrasos, fallos técnicos y seguridad"],
    ],
  });
  t.borders.assign({ style: "solid", fill: C.ink, width: 1.2 });
  t.cells.block({ row: 0, column: 0, rowCount: 1, columnCount: 2 }).assign({ fill: C.light, textStyle: { fontSize: 23, bold: true }, anchor: "middle" });
  t.cells.block({ row: 1, column: 0, rowCount: 5, columnCount: 2 }).assign({ textStyle: { fontSize: 20 }, anchor: "middle", margins: { left: 12, right: 12, top: 5, bottom: 5 } });
  text(s, "risk-formula", "Contingencia = (costos directos + indirectos) × porcentaje de riesgo", 150, 610, 850, 42, 22, { bold: true, color: C.red, align: "center" });
}

// 6
{
  const s = base("Presupuesto Final", 6);
  bullet(s, "La estimación se valida descomponiendo el trabajo, asignando horas y revisando los riesgos.", 145, 27, 70);
  const steps = [
    "1. Definir alcance, entregables y exclusiones.",
    "2. Crear la estructura de desglose del trabajo (EDT/WBS).",
    "3. Estimar horas, tarifas, responsables y dependencias.",
    "4. Agregar infraestructura, pruebas, documentación y capacitación.",
    "5. Incorporar costos indirectos, contingencia e impuestos.",
    "6. Actualizar el presupuesto con el avance y los cambios reales.",
  ];
  let y = 242;
  for (const line of steps) {
    text(s, `step-${y}`, line, 78, y, 1000, 50, 24, { bold: line.startsWith("1.") || line.startsWith("6.") });
    y += 56;
  }
  box(s, "final-callout", 78, 585, 980, 66, C.pale, C.red, 2);
  text(s, "final-formula", "TCO = Presupuesto inicial + Operación + Soporte + Mantenimiento", 95, 594, 946, 46, 24, { bold: true, color: C.red, align: "center" });
}

await fs.mkdir(RENDER, { recursive: true });
for (const [i, slide] of p.slides.items.entries()) {
  const png = await p.export({ slide, format: "png", scale: 1 });
  await fs.writeFile(`${RENDER}/slide-${i + 1}.png`, new Uint8Array(await png.arrayBuffer()));
}
const pptx = await PresentationFile.exportPptx(p);
await pptx.save(OUT);
console.log(OUT);
