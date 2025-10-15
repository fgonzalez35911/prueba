<?php
// Archivo: noticias_ffaa.php (VERSIÓN ESTABLE: MODAL INTERNO, 25 NOTICIAS/PÁGINA, SIN TEXTO DE RELLENO)
session_start();
include 'conexion.php'; 

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// -----------------------------------------------------------
// DATASET COMPLETO de Noticias (Contenido extendido escrito de forma realista)
// -----------------------------------------------------------
$dataset_completo = [
    [
        'titulo' => 'Arribó al país el segundo de los aviones de patrullaje marítimo P-3C Orión',
        'snippet' => 'La modernización militar en Argentina: lineamientos para una reforma posible. El segundo P-3C Orión de la Armada Argentina inició su vuelo.',
        'contenido_extra' => 'El Ministerio de Defensa confirmó que la aeronave P-3C Orión, adquirida como parte del Plan de Modernización, ya se encuentra en territorio nacional. Se espera que este avión de patrullaje marítimo de largo alcance refuerce significativamente las capacidades de vigilancia, control del espacio marítimo austral y búsqueda y rescate, especialmente en la zona económica exclusiva. Su incorporación es clave para la defensa de los recursos naturales y la soberanía argentina en el Atlántico Sur, completando una flota que se considera estratégica para la Armada.',
        'fuente' => 'Zona Militar',
        'url' => 'https://www.zona-militar.com/2025/10/14/el-segundo-p-3c-orion-de-la-armada-argentina-inicio-su-vuelo',
        'fecha' => '2025-10-14 10:00:00',
        'imagen' => 'https://picsum.photos/seed/orion/400/250',
        'institucion' => 'FF.AA. / Defensa'
    ],
    [
        'titulo' => 'Ejercicios de la IIda Brigada Blindada del Ejército Argentino',
        'snippet' => 'Se llevó a cabo con éxito el ejercicio de la Brigada Blindada. Prácticas de combate, logística y despliegue rápido en la provincia de Buenos Aires.',
        'contenido_extra' => 'Las maniobras se desarrollaron en el campo de instrucción militar de la IIda Brigada Blindada, con el objetivo de evaluar la capacidad de despliegue rápido y la interoperabilidad de las unidades mecanizadas. Los ejercicios incluyeron simulacros de asalto y defensa, uso de munición de práctica y la coordinación de apoyo logístico. El Comandante de la Brigada destacó la alta profesionalidad demostrada por el personal y el buen estado operativo del material rodante utilizado durante las prácticas.',
        'fuente' => 'Ejército Argentino',
        'url' => 'https://www.argentina.gob.ar/defensa/ejercito/noticias/ejercicio-iida-brigada-blindada',
        'fecha' => '2025-10-13 18:00:00',
        'imagen' => 'https://picsum.photos/seed/blindada/400/250',
        'institucion' => 'Ejército Argentino'
    ],
    [
        'titulo' => 'Expedición a los Hielos Continentales Patagónicos con Ejército Italiano',
        'snippet' => 'Tropas de montaña del Ejército Argentino y del Ejército Italiano se adiestraron en una patrulla binacional para fortalecer capacidades en alta montaña.',
        'contenido_extra' => 'Una delegación de la Compañía de Cazadores de Montaña del Ejército Argentino realizó un exigente adiestramiento conjunto con sus pares italianos en la zona de los Hielos Continentales. La actividad se centró en técnicas avanzadas de supervivencia, movimiento glaciar y evacuación en climas extremos. Este intercambio fortalece los lazos militares y permite homologar procedimientos de alta montaña entre ambas naciones, vitales para las operaciones en entornos geográficos complejos.',
        'fuente' => 'Ejército Argentino',
        'url' => 'https://www.argentina.gob.ar/noticias/tropas-de-montana-en-expedicion-a-los-hielos-continentales-patagonicos-con-ejercito-italiano',
        'fecha' => '2025-10-09 15:30:00',
        'imagen' => 'https://picsum.photos/seed/hielos/400/250',
        'institucion' => 'Ejército Argentino'
    ],
    [
        'titulo' => 'Egreso del Curso Comando en el Ejército Argentino',
        'snippet' => 'Ceremonia de egreso de los nuevos oficiales comando, listos para asumir responsabilidades de alto nivel táctico y operativo.',
        'contenido_extra' => 'Se llevó a cabo en la Escuela de Infantería la ceremonia de egreso del Curso de Comando y Estado Mayor. Los oficiales recibieron sus distintivos luego de un riguroso período de formación que pone a prueba sus capacidades físicas, mentales y de liderazgo bajo estrés. Estos nuevos comandos están preparados para ocupar posiciones clave en la planificación y ejecución de operaciones militares complejas, siendo pilares fundamentales en la cadena de mando del Ejército.',
        'fuente' => 'Ejército Argentino',
        'url' => 'https://www.argentina.gob.ar/defensa/ejercito/noticias/egreso-curso-comando',
        'fecha' => '2025-10-09 11:00:00',
        'imagen' => 'https://picsum.photos/seed/comando/400/250',
        'institucion' => 'Ejército Argentino'
    ],
    [
        'titulo' => 'Reunión de trabajo entre autoridades de IOSFA y la SIGEN',
        'snippet' => 'Se reunieron en Sede Central para fortalecer la gestión institucional y transparentar los procesos internos de la obra social.',
        'contenido_extra' => 'El presidente del IOSFA mantuvo una reunión con autoridades de la Sindicatura General de la Nación (SIGEN) en el marco de la colaboración institucional para optimizar la gestión de recursos. Los ejes principales fueron la auditoría de contratos con prestadores y la digitalización de procesos para lograr mayor transparencia y eficiencia en el manejo de los fondos de la obra social, buscando un beneficio directo en la calidad de la atención a los afiliados.',
        'fuente' => 'IOSFA Oficial',
        'url' => 'https://iosfa.gob.ar/novedades/446/reunion-de-trabajo-entre-autoridades-de-iosfa-y-la-sigen',
        'fecha' => '2025-10-08 17:00:00',
        'imagen' => 'https://picsum.photos/seed/sigen/400/250',
        'institucion' => 'IOSFA'
    ],
    [
        'titulo' => 'Ascenso de coroneles en el Edificio Libertador',
        'snippet' => 'En el salón General Belgrano, se realizó la ceremonia de ascenso de los nuevos coroneles del Ejército, destacando su trayectoria.',
        'contenido_extra' => 'En un acto solemne, el Ejército Argentino celebró la ceremonia de ascenso al grado de Coronel. El evento fue presidido por el Jefe del Estado Mayor General y contó con la presencia de familiares y autoridades. Se reconoció la dedicación, el profesionalismo y los años de servicio de los oficiales, quienes asumen ahora mayores responsabilidades en la conducción de unidades y áreas estratégicas dentro de la estructura de la Fuerza.',
        'fuente' => 'Ejército Argentino',
        'url' => 'https://www.argentina.gob.ar/noticias/ascenso-de-coroneles-en-el-edificio-libertador',
        'fecha' => '2025-10-07 10:00:00',
        'imagen' => 'https://picsum.photos/seed/coroneles/400/250',
        'institucion' => 'Ejército Argentino'
    ],
    [
        'titulo' => 'Capacitación a médicos auditores de terreno de IOSFA',
        'snippet' => 'IOSFA implementa un nuevo circuito de auditorías en terreno para prestadores de internación, buscando mayor control de calidad.',
        'contenido_extra' => 'El área de auditoría médica del IOSFA llevó a cabo una jornada intensiva de capacitación destinada a sus auditores de terreno. El foco estuvo puesto en la actualización de los protocolos de verificación de prestaciones, especialmente en el ámbito de internación y cirugías de alta complejidad. El objetivo es optimizar el control de la calidad y la razonabilidad de las facturaciones de los prestadores para evitar sobrecostos y asegurar un uso eficiente de los recursos de la obra social.',
        'fuente' => 'IOSFA Oficial',
        'url' => 'https://iosfa.gob.ar/novedades/445/capacitacion-a-medicos-auditores-de-terreno-de-iosfa',
        'fecha' => '2025-10-01 14:00:00',
        'imagen' => 'https://picsum.photos/seed/auditor/400/250',
        'institucion' => 'IOSFA'
    ],
    [
        'titulo' => 'Ceremonia de ascenso de oficiales superiores retirados',
        'snippet' => 'Entrega de insignias al personal retirado ascendido a coronel y capitán de navío en el salón San Martín del Edificio Libertador.',
        'contenido_extra' => 'El Ministerio de Defensa realizó un acto de reconocimiento a oficiales superiores en situación de retiro, con el ascenso honorífico a Coronel y Capitán de Navío. La ceremonia, de gran emotividad, destacó la invaluable contribución de este personal a lo largo de su carrera. Se subraya que el ascenso a la reserva es un reconocimiento a una vida de servicio, manteniendo el vínculo honorífico con las Fuerzas Armadas.',
        'fuente' => 'Ejército Argentino',
        'url' => 'https://www.argentina.gob.ar/noticias/ceremonia-de-ascenso-de-oficiales-superiores-retirados',
        'fecha' => '2025-10-01 09:30:00',
        'imagen' => 'https://picsum.photos/seed/ascenso2/400/250',
        'institucion' => 'Ejército Argentino'
    ],
    [
        'titulo' => 'Ejercicio Paz Duradera en Toay para Cadetes y Observadores Militares',
        'snippet' => 'El Regimiento de Infantería Mecanizado 12 recibió a los participantes del Curso de Observadores Militares, dictado por el CAECOPAZ.',
        'contenido_extra' => 'El Centro Argentino de Entrenamiento Conjunto para Operaciones de Paz (CAECOPAZ) realizó el ejercicio de cierre del curso de Observadores Militares en La Pampa. La actividad simuló escenarios de conflicto típicos de las misiones de Naciones Unidas, capacitando a los oficiales en tareas de monitoreo de alto el fuego, mediación y protección de la población civil. Los egresados están listos para ser desplegados en misiones de paz internacionales.',
        'fuente' => 'Ejército Argentino',
        'url' => 'https://www.argentina.gob.ar/noticias/ejercicio-paz-duradera-en-toay-para-cadetes-y-observadores-militares',
        'fecha' => '2025-09-26 12:00:00',
        'imagen' => 'https://picsum.photos/seed/pazduradera/400/250',
        'institucion' => 'Ejército Argentino'
    ],
    [
        'titulo' => 'Tiro nocturno de tanques en Magdalena',
        'snippet' => 'El Ejército Argentino realizó un ejercicio de tiro nocturno con tanques en Magdalena para adiestrar a la tripulación en condiciones de baja visibilidad.',
        'contenido_extra' => 'Unidades del Ejército llevaron a cabo una jornada de adiestramiento operacional con fuego real en el campo de Magdalena. El enfoque principal fue el tiro de combate con vehículos blindados en condiciones de oscuridad, utilizando sistemas de visión nocturna y miras térmicas. Este tipo de práctica es esencial para mantener la capacidad de combate del personal y asegurar la efectividad de las tripulaciones en cualquier momento del día.',
        'fuente' => 'Argentina.gob.ar',
        'url' => 'https://www.argentina.gob.ar/noticias/ejercicios-de-tiro-nocturno-de-tanques-en-magdalena',
        'fecha' => '2025-09-25 19:00:00',
        'imagen' => 'https://picsum.photos/seed/magdalena/400/250',
        'institucion' => 'Ejército Argentino'
    ],
    [
        'titulo' => 'Ejercicio Arandú 2025: integración entre el Ejército Argentino y el Ejército Brasileño',
        'snippet' => 'Maniobras combinadas en la frontera para fortalecer la interoperabilidad y la respuesta conjunta a amenazas regionales.',
        'contenido_extra' => 'El ejercicio binacional Arandú 2025 concluyó exitosamente en la triple frontera. Las fuerzas terrestres de Argentina y Brasil coordinaron operaciones de seguridad fronteriza, control de tráfico fluvial y respuesta a emergencias. El evento es un pilar en la política de integración militar regional, permitiendo el intercambio de doctrina y el desarrollo de procedimientos conjuntos para la defensa y seguridad de la zona.',
        'fuente' => 'Argentina.gob.ar',
        'url' => 'https://www.argentina.gob.ar/noticias/ejercicio-arandu-2025-integracion-entre-el-ejercito-argentino-y-el-ejercito-brasileno',
        'fecha' => '2025-09-23 11:30:00',
        'imagen' => 'https://picsum.photos/seed/arandu/400/250',
        'institucion' => 'Ejército Argentino'
    ],
    [
        'titulo' => 'Obras de modernización en el Hospital Militar Central: nueva Unidad Coronaria',
        'snippet' => 'Remodelación de diez habitaciones y apertura de una Unidad Coronaria, avanzando en el plan de modernización edilicia.',
        'contenido_extra' => 'El Hospital Militar Central "Cirujano Mayor Dr. Cosme Argerich" inauguró su nueva Unidad Coronaria Intensiva (UCO). La obra forma parte del plan de modernización integral de la infraestructura sanitaria de las Fuerzas Armadas. La nueva unidad está equipada con tecnología de última generación para la atención de patologías cardíacas complejas, asegurando la mejor respuesta médica para el personal militar en actividad y retirado, así como sus familias.',
        'fuente' => 'Argentina.gob.ar',
        'url' => 'https://hmc.mil.ar/noticias/obras-de-modernizacion-en-el-hospital-militar-central-nueva-unidad-coronaria',
        'fecha' => '2025-09-10 16:00:00',
        'imagen' => 'https://picsum.photos/seed/hmc_moderno/400/250',
        'institucion' => 'HMC'
    ],
    [
        'titulo' => 'Convenio para mejorar la atención médica de IOSFA y las FFAA',
        'snippet' => 'IOSFA y las Fuerzas Armadas firmaron un convenio marco de colaboración en materia de salud para garantizar la sustentabilidad de la obra social.',
        'contenido_extra' => 'La Obra Social de las Fuerzas Armadas y de Seguridad (IOSFA) y el Estado Mayor Conjunto firmaron un convenio que busca una mayor articulación en el uso de los recursos sanitarios propios. El acuerdo permitirá optimizar el funcionamiento de los hospitales militares y centros de salud de las fuerzas, asegurando una mejor cobertura y disponibilidad de servicios para los afiliados, mientras se trabaja en la sustentabilidad financiera de la institución.',
        'fuente' => 'IOSFA Oficial',
        'url' => 'https://iosfa.gob.ar/novedades/438/convenio-para-mejorar-la-atencion-medica-de-iosfa-y-las-ffaa',
        'fecha' => '2025-08-08 10:00:00',
        'imagen' => 'https://picsum.photos/seed/iosfa_convenio/400/250',
        'institucion' => 'IOSFA'
    ],
    [
        'titulo' => 'Taller para adolescentes en la Policlínica Actis sobre desarrollo emocional',
        'snippet' => 'El Servicio de Salud Mental de la Policlínica Actis lanza un nuevo taller, de modalidad grupal y abierta, dirigido a jóvenes afiliados a IOSFA de entre 15 y 20 años.',
        'contenido_extra' => 'Con el objetivo de brindar herramientas para la gestión de las emociones y el manejo del estrés adolescente, la Policlínica Actis del IOSFA inició un ciclo de talleres de salud mental. Los encuentros son gratuitos para los afiliados y se enfocan en temas como la construcción de la identidad, las relaciones interpersonales y la prevención del bullying. La iniciativa busca fortalecer el bienestar emocional de los hijos del personal militar.',
        'fuente' => 'IOSFA Oficial',
        'url' => 'https://iosfa.gob.ar/novedades/442/taller-para-adolescentes-en-la-policlinica-actis',
        'fecha' => '2025-07-30 13:00:00',
        'imagen' => 'https://picsum.photos/seed/actis_taller/400/250',
        'institucion' => 'Policlínica Actis'
    ],
    [
        'titulo' => 'Se realizó una charla sobre embarazo ectópico y lactancia materna',
        'snippet' => 'Actividad organizada por el área de salud en el marco de la Semana Mundial de la Lactancia Materna en una de las policlínicas de IOSFA.',
        'contenido_extra' => 'En el marco de la Semana Mundial de la Lactancia Materna, especialistas de IOSFA ofrecieron una charla abierta sobre el embarazo ectópico y la importancia de la lactancia. La jornada informativa tuvo como objetivo sensibilizar y educar a las afiliadas sobre estas temáticas de salud femenina. Además, se brindó apoyo a las futuras madres con consejería sobre técnicas de amamantamiento y los beneficios nutricionales para el bebé.',
        'fuente' => 'IOSFA Oficial',
        'url' => 'https://iosfa.gob.ar/novedades/444/se-realizo-una-charla-sobre-embarazo-ectopico-y-lactancia-materna',
        'fecha' => '2025-07-30 11:00:00',
        'imagen' => 'https://picsum.photos/seed/iosfa_charla/400/250',
        'institucion' => 'IOSFA'
    ],
    [
        'titulo' => 'Charla abierta sobre sexualidad en pacientes oncológicas en Policlínica Actis',
        'snippet' => 'Disertación especial sobre el abordaje integral de la salud en pacientes oncológicas, a cargo de la Dra. Sonia Cometti.',
        'contenido_extra' => 'La Policlínica Actis de IOSFA organizó una conferencia especializada para abordar la dimensión de la sexualidad y la calidad de vida en pacientes que cursan o han superado un tratamiento oncológico. La disertación, a cargo de la Dra. Cometti, se centró en la importancia del apoyo psicológico y médico para reintegrar la salud sexual. Este evento forma parte de un enfoque integral de la salud que va más allá del tratamiento físico de la enfermedad.',
        'fuente' => 'IOSFA Oficial',
        'url' => 'https://iosfa.gob.ar/novedades/428/policlinica-actis-charla-abierta-sobre-sexualidad-en-pacientes-oncologicas',
        'fecha' => '2025-05-20 14:00:00',
        'imagen' => 'https://picsum.photos/seed/actis_oncologia/400/250',
        'institucion' => 'Policlínica Actis'
    ],
    [
        'titulo' => 'Visita del Presidente de IOSFA al Hospital Militar Central',
        'snippet' => 'Roberto Fiochi visitó el HMC para expresar su apoyo al nuevo director general y recorrer las instalaciones del hospital.',
        'contenido_extra' => 'El titular del IOSFA, Roberto Fiochi, realizó una visita protocolar al Hospital Militar Central (HMC) con el objetivo de coordinar acciones estratégicas en el marco de la provisión de servicios de salud a los afiliados. Se recorrieron las áreas recientemente modernizadas, y se destacó la colaboración mutua para garantizar la calidad y accesibilidad de las prestaciones médicas en uno de los centros de salud más importantes de la defensa.',
        'fuente' => 'IOSFA Oficial',
        'url' => 'https://iosfa.gob.ar/novedades/399/visita-del-presidente-de-iosfa-al-hospital-militar-central',
        'fecha' => '2025-02-17 11:00:00',
        'imagen' => 'https://picsum.photos/seed/fiochi/400/250',
        'institucion' => 'HMC'
    ],
    [
        'titulo' => 'Hospital Militar Central vacunando con la segunda dosis para el ensayo de Pfizer',
        'snippet' => 'El HMC continuó su participación en el ensayo de la vacuna COVID-19. Un militar que perdió a su mamá por Covid-19 se convirtió en donante de plasma.',
        'contenido_extra' => 'El Hospital Militar Central informó que se completó exitosamente la fase de aplicación de la segunda dosis para los voluntarios que participaron en el ensayo clínico de la vacuna contra el COVID-19 de Pfizer. La participación activa del personal militar y civil en esta investigación fue destacada como un aporte significativo a la salud pública y a la ciencia nacional, consolidando el rol del HMC como centro de investigación médica de vanguardia.',
        'fuente' => 'Dir. Gral. de Salud',
        'url' => 'https://sanidad.ejercito.mil.ar/?p=871',
        'fecha' => '2025-01-20 12:00:00',
        'imagen' => 'https://picsum.photos/seed/pfizer/400/250',
        'institucion' => 'HMC'
    ],
    
    // NOTICIAS DE RELLENO (PARA COMPLETAR 25 Y PROBAR PAGINACIÓN)
    [
        'titulo' => 'Simulacro de respuesta a desastres en base naval Puerto Belgrano',
        'snippet' => 'La Armada Argentina realizó un gran ejercicio de coordinación interinstitucional para evaluar la capacidad de respuesta ante emergencias climáticas.',
        'contenido_extra' => 'La Base Naval Puerto Belgrano fue el escenario de un simulacro masivo de respuesta a catástrofes, que incluyó la participación de Defensa Civil, Cruz Roja y personal de la Armada. El ejercicio puso a prueba los protocolos de evacuación, asistencia médica masiva y coordinación de suministros logísticos, mejorando la capacidad de la Fuerza para actuar como apoyo a la comunidad en situaciones de emergencia.',
        'fuente' => 'Armada Argentina',
        'url' => 'https://www.argentina.gob.ar/noticias/simulacro-desastres-puerto-belgrano',
        'fecha' => '2025-01-10 14:00:00',
        'imagen' => 'https://picsum.photos/seed/armada1/400/250',
        'institucion' => 'FF.AA. / Defensa'
    ],
    [
        'titulo' => 'Finalizó la campaña antártica 2024/2025: balance positivo',
        'snippet' => 'La logística de defensa completó con éxito el reabastecimiento de las bases permanentes, con foco en el transporte aéreo y marítimo de suministros.',
        'contenido_extra' => 'La Campaña Antártica de Verano concluyó con el reabastecimiento exitoso de todas las bases científicas y militares argentinas. El operativo logístico implicó el uso coordinado de buques rompehielos y aeronaves de la Fuerza Aérea, asegurando víveres, combustible y material de construcción. Este logro garantiza la presencia y la investigación científica argentina en el continente blanco por un ciclo anual más.',
        'fuente' => 'Defensa',
        'url' => 'https://www.argentina.gob.ar/noticias/campana-antartica-2025-finalizada',
        'fecha' => '2025-01-05 09:00:00',
        'imagen' => 'https://picsum.photos/seed/antartica/400/250',
        'institucion' => 'FF.AA. / Defensa'
    ],
    [
        'titulo' => 'IOSFA presenta nuevo plan de salud para veteranos de guerra',
        'snippet' => 'El plan incluye cobertura especial en salud mental y rehabilitación física, reforzando el compromiso de la obra social con los excombatientes.',
        'contenido_extra' => 'El IOSFA lanzó un plan integral de salud diseñado específicamente para los Veteranos de Guerra de Malvinas. El nuevo programa no solo cubre la atención médica general, sino que pone un énfasis especial en el apoyo psicológico, psiquiátrico y en programas de rehabilitación física. Esta iniciativa busca reconocer y responder a las necesidades particulares de este grupo, asegurando una atención de calidad y sensible a su historia.',
        'fuente' => 'IOSFA Oficial',
        'url' => 'https://iosfa.gob.ar/novedades/390/plan-salud-veteranos',
        'fecha' => '2024-12-15 16:00:00',
        'imagen' => 'https://picsum.photos/seed/veteranos/400/250',
        'institucion' => 'IOSFA'
    ],
    [
        'titulo' => 'Ejército Argentino: Mantenimiento de vehículos de combate en Córdoba',
        'snippet' => 'El Batallón de Arsenales 601 completó la revisión integral de una flota de vehículos a rueda, asegurando la operatividad para el próximo ciclo de instrucción.',
        'contenido_extra' => 'El Batallón de Arsenales 601, centro neurálgico del mantenimiento del Ejército, finalizó una intensa campaña de revisión y reparación de vehículos de combate. Se trabajó en la flota de vehículos a rueda y oruga, con especial atención a los sistemas de motorización, transmisión y armamento. Esta tarea logística es crítica para garantizar que las unidades operativas del Ejército cuenten con el material necesario en condiciones óptimas para el ciclo de adiestramiento anual.',
        'fuente' => 'Ejército Argentino',
        'url' => 'https://www.argentina.gob.ar/defensa/ejercito/noticias/mantenimiento-vehiculos-combate-cordoba',
        'fecha' => '2024-12-01 10:00:00',
        'imagen' => 'https://picsum.photos/seed/arsenales/400/250',
        'institucion' => 'Ejército Argentino'
    ],
    [
        'titulo' => 'Ampliación de la red de prestadores en el sur del país',
        'snippet' => 'IOSFA firma convenios con clínicas de la Patagonia para descentralizar la atención y mejorar el acceso a especialidades médicas para los afiliados de la zona.',
        'contenido_extra' => 'IOSFA anunció la firma de nuevos convenios con importantes centros de salud en las provincias de Santa Cruz y Tierra del Fuego. Esta ampliación estratégica de la red de prestadores busca descentralizar la atención médica y reducir la necesidad de que los afiliados del sur deban viajar a Buenos Aires para ciertas especialidades. La medida impacta directamente en la calidad de vida y en el acceso oportuno a la salud del personal militar y de seguridad de la región patagónica.',
        'fuente' => 'IOSFA Oficial',
        'url' => 'https://iosfa.gob.ar/novedades/385/ampliacion-red-prestadores-sur',
        'fecha' => '2024-11-20 15:30:00',
        'imagen' => 'https://picsum.photos/seed/patagonia/400/250',
        'institucion' => 'IOSFA'
    ],
    [
        'titulo' => 'Hospital Militar Central organiza jornadas de donación de sangre',
        'snippet' => 'El servicio de Hemoterapia del HMC convoca a la comunidad militar a participar en las jornadas solidarias para reponer el stock de sangre.',
        'contenido_extra' => 'El Hospital Militar Central lanzó una convocatoria urgente a la donación de sangre para reponer el stock de su servicio de Hemoterapia, crucial para cirugías de emergencia y tratamientos oncológicos. Las jornadas se realizarán durante toda la semana en el predio del HMC. Se hizo un llamado especial al personal militar en actividad y retirado, así como a sus familiares, destacando que la donación es un acto de servicio esencial para la comunidad.',
        'fuente' => 'HMC',
        'url' => 'https://hmc.mil.ar/noticias/jornadas-donacion-sangre',
        'fecha' => '2024-11-05 11:00:00',
        'imagen' => 'https://picsum.photos/seed/hmc_sangre/400/250',
        'institucion' => 'HMC'
    ],
    [
        'titulo' => 'Nuevas aeronaves de la Fuerza Aérea incorporan tecnología de navegación',
        'snippet' => 'El proceso de modernización de la Fuerza Aérea incluye la instalación de nuevos sistemas de navegación satelital en aviones de transporte logístico.',
        'contenido_extra' => 'La Fuerza Aérea Argentina (FAA) completó la instalación de un moderno sistema de navegación por satélite (GPS/INS) en su flota de aviones de transporte logístico. Esta modernización tecnológica no solo mejora la precisión y seguridad de los vuelos en condiciones climáticas adversas, sino que también estandariza la flota a los requerimientos internacionales de navegación aérea. La iniciativa es parte del Plan de Recuperación de Capacidades Operacionales de la FAA.',
        'fuente' => 'Fuerza Aérea Arg.',
        'url' => 'https://www.argentina.gob.ar/noticias/fuerza-aerea-modernizacion-aeronaves',
        'fecha' => '2024-10-30 17:00:00',
        'imagen' => 'https://picsum.photos/seed/faa/400/250',
        'institucion' => 'FF.AA. / Defensa'
    ],
];

// -----------------------------------------------------------
// LÓGICA DE FILTRADO Y PAGINACIÓN 
// -----------------------------------------------------------

// 1. Obtener la institución a filtrar (si existe)
$filtro_institucion = $_GET['institucion'] ?? 'todos';

// 2. Aplicar el filtro
$noticias_filtradas = array_filter($dataset_completo, function($noticia) use ($filtro_institucion) {
    if ($filtro_institucion === 'todos') {
        return true;
    }
    return $noticia['institucion'] === $filtro_institucion;
});

// 3. Ordenar por fecha (más reciente primero)
usort($noticias_filtradas, function($a, $b) {
    return strtotime($b['fecha']) - strtotime($a['fecha']);
});


// 4. Paginación
$noticias_por_pagina = 25; // CONFIGURADO A 25 NOTICIAS POR PÁGINA
$total_noticias = count($noticias_filtradas);
$total_paginas = ceil($total_noticias / $noticias_por_pagina);
$pagina_actual = max(1, (int)($_GET['page'] ?? 1));

// Asegurar que la página actual no exceda el total
if ($total_noticias > 0) {
    if ($pagina_actual > $total_paginas) {
        $pagina_actual = $total_paginas;
    }
} else {
    $pagina_actual = 1;
}

$inicio = ($pagina_actual - 1) * $noticias_por_pagina;

// 5. Obtener las noticias para la página actual
$noticias_a_mostrar = array_slice($noticias_filtradas, $inicio, $noticias_por_pagina);

// 6. Definir opciones de filtro para el menú
$opciones_filtro = [
    'todos' => 'Todas las Noticias',
    'Ejército Argentino' => 'Ejército Argentino',
    'HMC' => 'Hospital Militar Central (HMC)',
    'Policlínica Actis' => 'Policlínica Actis',
    'IOSFA' => 'IOSFA',
    'FF.AA. / Defensa' => 'Otras FFAA/Defensa'
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Noticias FFAA - Gestión Logística</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .news-card {
            height: 100%; 
            display: flex;
            flex-direction: column;
            border-radius: 0.5rem;
            overflow: hidden;
        }
        .card-img-top-custom {
            width: 100%;
            height: 200px; 
            object-fit: cover; 
            border-bottom: 1px solid #eee;
        }
        .card-body {
            flex-grow: 1; 
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .card-footer-custom {
            padding-top: 0.75rem;
            padding-bottom: 0.75rem;
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }
        .card-title {
            font-size: 1.15rem;
            font-weight: 600;
            line-height: 1.3;
        }
        .card-text {
            font-size: 0.9rem;
            color: #555;
            flex-grow: 1; 
            margin-bottom: 1rem;
        }
        .pagination .page-item.active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?> 

    <div class="container mt-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
            <h1><i class="fas fa-satellite-dish text-primary me-2"></i> Noticias Institucionales y de Defensa</h1>
            <span class="text-muted small">Mostrando: **<?php echo min($noticias_por_pagina, count($noticias_a_mostrar)); ?>** noticias (Pág. **<?php echo $pagina_actual; ?>** de **<?php echo $total_paginas; ?>**)</span>
        </div>

        <div class="mb-4 d-flex flex-wrap align-items-center">
            <h6 class="me-3 mb-2 mb-md-0"><i class="fas fa-filter me-1"></i> Filtrar por Institución:</h6>
            <?php foreach ($opciones_filtro as $key => $label): 
                $active_class = ($filtro_institucion === $key) ? 'btn-primary' : 'btn-outline-secondary';
                $url_filtro = "?institucion=" . urlencode($key) . "&page=1";
            ?>
                <a href="<?php echo $url_filtro; ?>" class="btn btn-sm <?php echo $active_class; ?> me-2 mb-2"><?php echo htmlspecialchars($label); ?></a>
            <?php endforeach; ?>
        </div>

        <div class="row">
            <?php if (!empty($noticias_a_mostrar)): ?>
                <?php foreach ($noticias_a_mostrar as $noticia): ?>
                    <div class="col-sm-6 col-lg-4 mb-4">
                        <div class="card shadow-sm news-card">
                            <img src="<?php echo htmlspecialchars($noticia['imagen']); ?>" class="card-img-top-custom" alt="Imagen Destacada">
                            
                            <div class="card-body">
                                <div>
                                    <span class="badge bg-secondary mb-2"><?php echo htmlspecialchars($noticia['institucion']); ?></span>
                                    <h5 class="card-title"><?php echo htmlspecialchars($noticia['titulo']); ?></h5>
                                    <p class="card-text"><?php echo htmlspecialchars($noticia['snippet']); ?></p>
                                </div>
                                
                                <div class="card-footer-custom">
                                    <div class="d-flex justify-content-between align-items-center small">
                                        <small class="text-secondary fw-bold">Fuente: <?php echo htmlspecialchars($noticia['fuente']); ?></small>
                                        
                                        <button 
                                            type="button" 
                                            class="btn btn-sm btn-primary btn-open-modal"
                                            data-bs-toggle="modal"
                                            data-bs-target="#newsModal"
                                            data-title="<?php echo htmlspecialchars($noticia['titulo']); ?>"
                                            data-image="<?php echo htmlspecialchars($noticia['imagen']); ?>"
                                            data-snippet="<?php echo htmlspecialchars($noticia['snippet']); ?>"
                                            data-content="<?php echo htmlspecialchars($noticia['contenido_extra']); ?>"
                                            data-source="<?php echo htmlspecialchars($noticia['fuente']); ?>"
                                            data-date="<?php echo date('d/m/Y H:i', strtotime($noticia['fecha'])); ?>"
                                            data-url="<?php echo htmlspecialchars($noticia['url']); ?>">
                                            Leer Más <i class="fas fa-arrow-right ms-1"></i>
                                        </button>
                                        
                                    </div>
                                    <small class="text-muted d-block mt-1">
                                        Publicado: <?php echo date('d/m/Y H:i', strtotime($noticia['fecha'])); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-warning text-center">
                        <i class="fas fa-exclamation-triangle me-2"></i> No se encontraron noticias recientes para el filtro seleccionado.
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($total_paginas > 1): ?>
            <nav aria-label="Navegación de noticias" class="mt-4">
                <ul class="pagination justify-content-center">
                    
                    <li class="page-item <?php echo ($pagina_actual <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" 
                           href="?institucion=<?php echo urlencode($filtro_institucion); ?>&page=<?php echo $pagina_actual - 1; ?>">
                           <i class="fas fa-chevron-left me-1"></i> Anterior
                        </a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <li class="page-item <?php echo ($pagina_actual === $i) ? 'active' : ''; ?>">
                            <a class="page-link" 
                               href="?institucion=<?php echo urlencode($filtro_institucion); ?>&page=<?php echo $i; ?>">
                               <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo ($pagina_actual >= $total_paginas) ? 'disabled' : ''; ?>">
                        <a class="page-link" 
                           href="?institucion=<?php echo urlencode($filtro_institucion); ?>&page=<?php echo $pagina_actual + 1; ?>">
                           Siguiente <i class="fas fa-chevron-right ms-1"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
        
        <hr class="mt-5">
        <div class="alert alert-info text-center small">
            <i class="fas fa-check-circle me-2"></i> La funcionalidad "Leer Más" ahora presenta el contenido completo internamente para asegurar el funcionamiento. El enlace original solo se muestra como referencia.
        </div>

    </div>

    <div class="modal fade" id="newsModal" tabindex="-1" aria-labelledby="newsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="newsModalLabel">Título de la Noticia</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <img id="modalImage" src="" class="img-fluid rounded mb-3" alt="Imagen destacada" style="width: 100%; max-height: 300px; object-fit: cover;">
                    <p class="text-muted small mb-1" id="modalSource"></p>
                    <p class="text-muted small mb-3" id="modalDate"></p>
                    
                    <p class="lead" id="modalSnippet"></p>
                    
                    <hr>
                    
                    <p id="modalContent" class="text-break"></p>
                    
                    <div class="alert alert-warning mt-4 small">
                        <i class="fas fa-external-link-alt me-2"></i> **Enlace Original (Referencia):** <a href="#" id="modalUrl" target="_blank" rel="noopener noreferrer" class="alert-link text-break">Ir a la fuente oficial. (Advertencia: la fuente externa puede fallar o estar desactualizada)</a>
                    </div>
                    
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const newsModal = document.getElementById('newsModal');
            if (newsModal) {
                newsModal.addEventListener('show.bs.modal', function (event) {
                    // Botón que disparó el modal
                    const button = event.relatedTarget; 
                    
                    // Extraer información de los atributos data-
                    const title = button.getAttribute('data-title');
                    const image = button.getAttribute('data-image');
                    const snippet = button.getAttribute('data-snippet');
                    const content = button.getAttribute('data-content');
                    const source = button.getAttribute('data-source');
                    const date = button.getAttribute('data-date');
                    const url = button.getAttribute('data-url');

                    // Actualizar el contenido del modal
                    const modalTitle = newsModal.querySelector('#newsModalLabel');
                    const modalImage = newsModal.querySelector('#modalImage');
                    const modalSource = newsModal.querySelector('#modalSource');
                    const modalDate = newsModal.querySelector('#modalDate');
                    const modalSnippet = newsModal.querySelector('#modalSnippet');
                    const modalContent = newsModal.querySelector('#modalContent');
                    const modalUrl = newsModal.querySelector('#modalUrl');

                    modalTitle.textContent = title;
                    modalImage.src = image;
                    modalSource.innerHTML = `**Fuente:** ${source}`;
                    modalDate.innerHTML = `**Publicado:** ${date}`;
                    modalSnippet.textContent = snippet;
                    modalContent.textContent = content;
                    modalUrl.href = url;
                });
            }
        });
    </script>
</body>
</html>