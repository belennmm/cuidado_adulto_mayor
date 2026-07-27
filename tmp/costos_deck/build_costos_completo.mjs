import fs from "node:fs/promises";
import { Presentation, PresentationFile } from "@oai/artifact-tool";

const OUT = "C:/Users/leejo/Desktop/cuidado_adulto_mayor/output/presentaciones/Resumen_Mapa_Calculo_Costos_SW_Organizate.pptx";
const RENDER = "C:/Users/leejo/Desktop/cuidado_adulto_mayor/tmp/costos_deck/render_completo";
const p = Presentation.create({ slideSize: { width: 1280, height: 720 } });
const C = { red:"#c71920", dark:"#721014", pale:"#fff3f3", ink:"#151515", gray:"#606060", line:"#b8b8b8", light:"#f5f5f5", white:"#ffffff" };

function rect(s,n,x,y,w,h,fill="none",line="none",lw=0){
  return s.shapes.add({geometry:"rect",name:n,position:{left:x,top:y,width:w,height:h},fill,line:{style:"solid",fill:line,width:lw}});
}
function txt(s,n,v,x,y,w,h,size=25,o={}){
  const z=s.shapes.add({geometry:"textbox",name:n,position:{left:x,top:y,width:w,height:h},fill:"none",line:{style:"solid",fill:"none",width:0}});
  z.text=v; z.text.style={fontFamily:"Arial",fontSize:size,color:o.color||C.ink,bold:!!o.bold,alignment:o.align||"left",verticalAlignment:o.valign||"middle"};
  return z;
}
function base(title,num){
  const s=p.slides.add(); s.background.fill=C.white;
  rect(s,"sidebar",1152,0,128,720,C.dark); rect(s,"accent",1152,0,10,720,"#941319");
  txt(s,"title",title,20,24,1100,72,40,{bold:true,color:C.red});
  rect(s,"rule",14,108,1115,3,C.ink);
  txt(s,"footer","Planificación y Estimación · Organízate",160,676,720,24,18);
  txt(s,"page",String(num),930,676,80,24,18,{align:"center"});
  txt(s,"side-page",`${num} de 9`,1170,676,88,24,15,{color:C.white,align:"center"});
  s.speakerNotes.textFrame.setText("[Sources]\n- Presentación: 08. Estimacion de tiempo y esfuerzo.Resumen.pptx.\n- Proyecto local Organízate: README.md, rutas/controladores, migraciones, pruebas, docker-compose.yml, frontend/package.json y backend/composer.json.");
  return s;
}
function bullet(s,v,y,size=24,h=55){
  txt(s,`dot-${y}`,"⊙",22,y,30,38,size+3,{bold:true,color:C.red});
  txt(s,`b-${y}`,v,58,y-2,1050,h,size);
}
function table(s,name,values,x,y,w,h,widths,font=20){
  const t=s.tables.add({rows:values.length,columns:values[0].length,left:x,top:y,width:w,height:h,columnWidths:widths,values});
  t.borders.assign({style:"solid",fill:C.ink,width:1});
  t.cells.block({row:0,column:0,rowCount:1,columnCount:values[0].length}).assign({fill:C.light,textStyle:{fontSize:font+2,bold:true},anchor:"middle",margins:{left:10,right:10,top:5,bottom:5}});
  t.cells.block({row:1,column:0,rowCount:values.length-1,columnCount:values[0].length}).assign({textStyle:{fontSize:font},anchor:"middle",margins:{left:10,right:10,top:4,bottom:4}});
  return t;
}

// 1
{
  const s=base("Cálculo de Costos del Software",1);
  txt(s,"subtitle","Resumen y mapa conceptual aplicado a “Organízate”",58,170,980,70,36,{bold:true});
  bullet(s,"Estimar costos significa convertir alcance y funcionalidad en esfuerzo, tiempo, personas y dinero.",285,28,78);
  bullet(s,"La factibilidad depende de restricciones organizativas, económicas, técnicas y de calendario.",405,27,75);
  rect(s,"formula",64,555,950,68,C.pale,C.red,2);
  txt(s,"formula-text","Alcance → Tamaño → Esfuerzo → Tiempo y recursos → Costo total",85,565,910,46,27,{bold:true,color:C.red,align:"center"});
}
// 2
{
  const s=base("Mapa del Proceso de Estimación",2);
  const nodes=[
    ["1. Alcance","Actores, casos de uso y requisitos"],
    ["2. Tamaño","PCU sin ajustar"],
    ["3. Ajuste","Complejidad técnica y ambiente"],
    ["4. Esfuerzo","PCUA × factor de conversión"],
    ["5. Plan","Distribución, personas y duración"],
    ["6. Costo","Tarifas + indirectos + riesgos"],
  ];
  let y=145;
  for(let i=0;i<nodes.length;i++){
    rect(s,`node-${i}`,92,y,900,62,i===5?C.pale:C.light,i===5?C.red:C.line,i===5?2:1);
    txt(s,`head-${i}`,nodes[i][0],112,y+7,210,48,24,{bold:true,color:C.red});
    txt(s,`body-${i}`,nodes[i][1],330,y+7,635,48,23);
    if(i<nodes.length-1) txt(s,`arrow-${i}`,"↓",1005,y+43,55,45,28,{bold:true,color:C.red,align:"center"});
    y+=82;
  }
}
// 3
{
  const s=base("Métodos y Variables que se Estiman",3);
  table(s,"methods",[
    ["Enfoque","Punto de partida","Uso"],
    ["COCOMO / Putnam","Tamaño en líneas de código","Esfuerzo, tiempo y costo"],
    ["Puntos de Función","Transacciones y archivos","Funcionalidad independiente del lenguaje"],
    ["Puntos de Casos de Uso","Actores, casos de uso y complejidad","Proyectos definidos mediante casos de uso"],
  ],42,155,1070,240,[270,350,450],19);
  bullet(s,"Variables: tamaño del software; esfuerzo en H/H, H/D o H/M; duración; cantidad de personas; costo.",445,25,68);
  bullet(s,"Para “Organízate”, PCU es apropiado: existen tres roles, múltiples flujos funcionales y factores técnicos identificables.",545,25,70);
}
// 4
{
  const s=base("Paso 1 · Puntos de Casos de Uso",4);
  txt(s,"formula","PCU = FPA + FPCU",70,140,980,58,30,{bold:true,color:C.red,align:"center"});
  table(s,"weights",[
    ["Componente","Clasificación","Peso"],
    ["Actor","Simple: API / sistema","1"],
    ["Actor","Medio: protocolo o texto","2"],
    ["Actor","Complejo: persona con interfaz gráfica","3"],
    ["Caso de uso","Simple: menos de 4 transacciones","5"],
    ["Caso de uso","Medio: de 4 a 7 transacciones","10"],
    ["Caso de uso","Complejo: más de 7 transacciones","15"],
  ],55,225,1040,350,[250,610,180],19);
  txt(s,"note","FPA suma actores ponderados; FPCU suma casos de uso ponderados.",150,600,850,42,22,{bold:true,align:"center"});
}
// 5
{
  const s=base("Paso 2 · Ajustar por Tecnología y Ambiente",5);
  txt(s,"pcua","PCUA = PCU × FCT × FA",75,135,980,58,30,{bold:true,color:C.red,align:"center"});
  table(s,"factors",[
    ["Factor","Fórmula","Aspectos relacionados"],
    ["FCT","0.6 + 0.01 × Σ(peso × valor)","Distribución, rendimiento, reutilización, instalación, uso, portabilidad, cambio, concurrencia, seguridad, terceros y entrenamiento"],
    ["FA","1.4 − 0.03 × Σ(peso × valor)","Experiencia, capacidad del analista, motivación, estabilidad de requisitos, dedicación y dificultad del lenguaje"],
  ],42,235,1070,250,[160,315,595],18);
  bullet(s,"Cada factor se valora de 0 a 5 según su relevancia o condición en el proyecto.",520,24,55);
  bullet(s,"En “Organízate” influyen seguridad por roles, API, Docker, PostgreSQL, facilidad de uso y experiencia con Laravel/JavaScript.",585,23,62);
}
// 6
{
  const s=base("Paso 3 · Del PCUA al Esfuerzo",6);
  rect(s,"call",65,145,970,80,C.pale,C.red,2);
  txt(s,"effort","Esfuerzo funcional (E) = PCUA × FC",90,158,920,48,30,{bold:true,color:C.red,align:"center"});
  table(s,"conversion",[
    ["Condición ambiental","Factor de conversión (FC)"],
    ["Riesgo controlado: ≤ 2 factores desfavorables","20 H/H por PCUA"],
    ["Riesgo medio: 3 o 4 factores desfavorables","28 H/H por PCUA"],
    ["Riesgo alto: ≥ 5 factores desfavorables","Revisar el proyecto; existe posibilidad de fracaso"],
  ],85,275,930,230,[590,340],20);
  bullet(s,"El esfuerzo PCU cubre la funcionalidad; después se agregan análisis, diseño, pruebas y otras actividades.",545,24,60);
}
// 7
{
  const s=base("Distribución del Esfuerzo Total",7);
  table(s,"distribution",[
    ["Actividad","Porcentaje","Aplicación en Organízate"],
    ["Análisis","10 %","Requisitos, roles, reglas y criterios de aceptación"],
    ["Diseño","20 %","Arquitectura, datos, API, seguridad, UX/UI"],
    ["Implementación","40 %","Frontend, Laravel, PostgreSQL e integración"],
    ["Pruebas","15 %","Unitarias, DOM, endpoints, flujos y regresión"],
    ["Otras actividades","15 %","Gestión, documentación, despliegue y coordinación"],
  ],40,160,1075,365,[250,170,655],19);
  rect(s,"relation",115,560,920,62,C.pale,C.red,2);
  txt(s,"relation-text","Si implementación = 40 %, entonces esfuerzo total = esfuerzo de implementación ÷ 0.40",135,570,880,42,23,{bold:true,color:C.red,align:"center"});
}
// 8
{
  const s=base("Tiempo, Personas y Costo",8);
  table(s,"costs",[
    ["Indicador","Cálculo"],
    ["Tiempo de desarrollo","TDES = esfuerzo total ÷ cantidad de personas"],
    ["Tarifa horaria promedio","THP = salario promedio mensual ÷ 160"],
    ["Costo por H/H","C = esfuerzo total en H/H × K × THP"],
    ["Costo por H/M","C = esfuerzo total en H/M × K × salario promedio mensual"],
    ["Costos indirectos","K entre 1.5 y 2.0, según la referencia"],
  ],60,150,1020,335,[355,665],21);
  bullet(s,"Agregar infraestructura, licencias, equipos, servidor, dominio, respaldos, energía, capacitación e impuestos.",525,23,55);
  bullet(s,"Agregar contingencia por cambios de alcance, retrasos, seguridad, fallos técnicos y retrabajo.",590,23,52);
}
// 9
{
  const s=base("Esquema Final del Presupuesto",9);
  const lines=[
    ["Diseño","Análisis + arquitectura + base de datos + UX/UI + documentación"],
    ["Implementación","Frontend + backend + datos + autenticación + integración"],
    ["Calidad","Pruebas + correcciones + revisión + retrabajo"],
    ["Entrega","Docker/servidor + configuración + migración + manuales + capacitación"],
    ["Ciclo de vida","Soporte + mantenimiento correctivo, adaptativo y evolutivo"],
  ];
  let y=145;
  for(let i=0;i<lines.length;i++){
    txt(s,`lh-${i}`,lines[i][0],65,y,225,54,24,{bold:true,color:C.red});
    txt(s,`lb-${i}`,lines[i][1],300,y,775,54,22);
    rect(s,`lr-${i}`,65,y+54,1010,1,C.line); y+=76;
  }
  rect(s,"total",65,555,1010,78,C.pale,C.red,2);
  txt(s,"total-text","Costo total = costos directos + indirectos + contingencia + operación y mantenimiento",90,567,960,50,25,{bold:true,color:C.red,align:"center"});
}

await fs.mkdir(RENDER,{recursive:true});
for(const [i,slide] of p.slides.items.entries()){
  const png=await p.export({slide,format:"png",scale:1});
  await fs.writeFile(`${RENDER}/slide-${i+1}.png`,new Uint8Array(await png.arrayBuffer()));
  const layout=await slide.export({format:"layout"});
  await fs.writeFile(`${RENDER}/slide-${i+1}.layout.json`,await layout.text());
}
const montage=await p.export({format:"png",montage:true,scale:1});
await fs.writeFile(`${RENDER}/montage.png`,new Uint8Array(await montage.arrayBuffer()));
const pptx=await PresentationFile.exportPptx(p);
await pptx.save(OUT);
console.log(OUT);
