<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1a1a1a">
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="../img/favicon-180.png">
    <script src="../js/theme.js?v=20260803-3"></script>
    <title>Vende tu Auto | CARPRIX</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../css/styles.css?v=20260803-3">
    <link rel="stylesheet" href="../css/vende.css?v=20260828-1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-dark">

    <header class="main-header">
        <nav class="container nav-flex">
            <div class="logo">
                <a href="../index.php" class="brand-logo-link" aria-label="CARPRIX - Inicio">
                    <img class="brand-logo brand-logo-full theme-logo theme-logo-dark" src="../img/brand/logo-carprix-dark.svg" alt="CARPRIX - Confianza que te mueve">
                    <img class="brand-logo brand-logo-full theme-logo theme-logo-light" src="../img/brand/logo-carprix-light.svg" alt="CARPRIX - Confianza que te mueve">
                    <img class="brand-logo brand-logo-mobile theme-logo theme-logo-dark" src="../img/brand/logo-carprix-wordmark-dark.svg" alt="CARPRIX">
                    <img class="brand-logo brand-logo-mobile theme-logo theme-logo-light" src="../img/brand/logo-carprix-wordmark-light.svg" alt="CARPRIX">
                </a>
            </div>

            <ul class="nav-menu" id="nav-menu">
                <li><a href="catalogo.php">Compra</a></li>
                <li><a href="vende.php" class="green-text" aria-current="page">Vende</a></li>
                <li><a href="nosotros.php">Nosotros</a></li>
                <li><a href="contacto.php">Contacto</a></li>
                <li><a href="../operativo/login.php" class="btn-outline" data-operativo-access>Iniciar Sesión</a></li>
            </ul>

            <div class="nav-actions">
                <button type="button" class="theme-toggle" id="theme-toggle" data-theme-toggle aria-label="Cambiar tema" title="Cambiar tema">
                    <i class="fas fa-sun" aria-hidden="true"></i>
                    <span class="sr-only">Cambiar tema</span>
                </button>
                <div class="menu-toggle" id="mobile-menu" role="button" tabindex="0" aria-label="Abrir menú" aria-controls="nav-menu" aria-expanded="false">
                    <i class="fas fa-bars" aria-hidden="true"></i>
                </div>
            </div>
        </nav>
    </header>

    <main class="vende-main">
        <section class="vende-intro" aria-labelledby="vende-title">
            <div class="container">
                <div class="vende-heading">
                    <span class="vende-kicker">VENDE TU AUTO CON CARPRIX</span>
                    <h1 id="vende-title">Descubre lo fácil que es vender tu auto</h1>
                    <p>
                        Comparte tus datos y los de tu vehículo. Nuestro equipo revisará la información,
                        coordinará la inspección y te acompañará durante todo el proceso.
                    </p>
                    <span class="vende-subtitle">Solo sigue estos 3 pasos:</span>
                </div>

                <div class="process-grid" aria-label="Proceso para vender tu auto">
                    <article class="process-card process-card-contact">
                        <div class="process-overlay"></div>
                        <div class="process-content">
                            <span class="process-number">1</span>
                            <div>
                                <h2>Envíanos tu información</h2>
                                <p>Déjanos tus datos de contacto para iniciar la solicitud.</p>
                            </div>
                        </div>
                    </article>

                    <article class="process-card process-card-visit">
                        <div class="process-overlay"></div>
                        <div class="process-content">
                            <span class="process-number">2</span>
                            <div>
                                <h2>Agenda la inspección</h2>
                                <p>Revisaremos el vehículo, su kilometraje y documentación.</p>
                            </div>
                        </div>
                    </article>

                    <article class="process-card process-card-payment">
                        <div class="process-overlay"></div>
                        <div class="process-content">
                            <span class="process-number">3</span>
                            <div>
                                <h2>Recibe tu pago</h2>
                                <p>Concluye la operación de forma clara, rápida y segura.</p>
                            </div>
                        </div>
                    </article>
                </div>

                <a class="intro-cta" href="#solicitud-venta">¿Listo? Envía tu información <i class="fas fa-arrow-down" aria-hidden="true"></i></a>
            </div>
        </section>

        <section class="vende-form-section" id="solicitud-venta" aria-labelledby="form-title">
            <div class="container">
                <form id="form-vende" class="vende-wizard" novalidate>
                    <h2 id="form-title" class="sr-only">Solicitud para vender tu auto</h2>

                    <div class="wizard-progress" aria-label="Progreso de la solicitud">
                        <div class="progress-line" aria-hidden="true"></div>

                        <button type="button" class="wizard-step is-active" data-step-indicator="1" aria-current="step">
                            <span class="wizard-step-number">1</span>
                            <span class="wizard-step-label">Contacto</span>
                        </button>

                        <button type="button" class="wizard-step" data-step-indicator="2" tabindex="-1">
                            <span class="wizard-step-number">2</span>
                            <span class="wizard-step-label">Auto</span>
                        </button>

                        <button type="button" class="wizard-step" data-step-indicator="3" tabindex="-1">
                            <span class="wizard-step-number">3</span>
                            <span class="wizard-step-label">Finalizar</span>
                        </button>
                    </div>

                    <div id="vende-mensaje" class="form-status" role="status" aria-live="polite"></div>

                    <section class="wizard-panel is-active" data-step-panel="1" aria-labelledby="step-one-title">
                        <div class="panel-heading">
                            <span class="panel-number">1</span>
                            <h3 id="step-one-title">Datos de contacto</h3>
                        </div>

                        <div class="form-grid form-grid-two">
                            <div class="form-group">
                                <label for="v-nombre">Nombre <span aria-hidden="true">*</span></label>
                                <input type="text" id="v-nombre" autocomplete="given-name" placeholder="Nombre" required>
                                <small class="field-error"></small>
                            </div>

                            <div class="form-group">
                                <label for="v-apellido-paterno">Apellido paterno <span aria-hidden="true">*</span></label>
                                <input type="text" id="v-apellido-paterno" autocomplete="family-name" placeholder="Apellido paterno" required>
                                <small class="field-error"></small>
                            </div>

                            <div class="form-group">
                                <label for="v-apellido-materno">Apellido materno <span aria-hidden="true">*</span></label>
                                <input type="text" id="v-apellido-materno" placeholder="Apellido materno" required>
                                <small class="field-error"></small>
                            </div>

                            <div class="form-group">
                                <label for="v-tel">Teléfono de contacto <span aria-hidden="true">*</span></label>
                                <input type="tel" id="v-tel" autocomplete="tel" inputmode="tel" placeholder="33 3133 7865" required>
                                <small class="field-error"></small>
                            </div>

                            <div class="form-group form-group-full">
                                <label for="v-email">Correo electrónico <span aria-hidden="true">*</span></label>
                                <input type="email" id="v-email" autocomplete="email" inputmode="email" placeholder="correo@ejemplo.com" required>
                                <small class="field-error"></small>
                            </div>
                        </div>

                        <div class="wizard-actions wizard-actions-end">
                            <button type="button" class="wizard-btn wizard-btn-primary" data-next-step="2">
                                Siguiente <i class="fas fa-arrow-right" aria-hidden="true"></i>
                            </button>
                        </div>
                    </section>

                    <section class="wizard-panel" data-step-panel="2" aria-labelledby="step-two-title" hidden>
                        <div class="panel-heading">
                            <span class="panel-number">2</span>
                            <h3 id="step-two-title">Datos del auto que quieres vender</h3>
                        </div>

                        <div class="form-grid form-grid-two">
                            <div class="form-group">
                                <label for="v-marca">Marca <span aria-hidden="true">*</span></label>
                                <select id="v-marca" required>
                                    <option value="">Selecciona marca</option>
                                </select>
                                <input class="catalog-other-field" type="text" id="v-marca-otro" maxlength="50" placeholder="Escribe la marca" hidden>
                                <small class="field-error"></small>
                            </div>

                            <div class="form-group">
                                <label for="v-modelo">Modelo <span aria-hidden="true">*</span></label>
                                <select id="v-modelo" required disabled>
                                    <option value="">Selecciona primero una marca</option>
                                </select>
                                <input class="catalog-other-field" type="text" id="v-modelo-otro" maxlength="100" placeholder="Escribe el modelo" hidden>
                                <small class="field-error"></small>
                            </div>

                            <div class="form-group">
                                <label for="v-version">Versión <span aria-hidden="true">*</span></label>
                                <input type="text" id="v-version" placeholder="Ingresa versión de tu auto" required>
                                <small class="field-error"></small>
                            </div>

                            <div class="form-group">
                                <label for="v-anio">Año <span aria-hidden="true">*</span></label>
                                <select id="v-anio" required>
                                    <option value="">Selecciona año</option>
                                </select>
                                <input class="catalog-other-field" type="number" id="v-anio-otro" min="1980" max="2100" inputmode="numeric" placeholder="Escribe el año" hidden>
                                <small class="field-error"></small>
                            </div>

                            <div class="form-group">
                                <label for="v-color">Color <span aria-hidden="true">*</span></label>
                                <select id="v-color" required>
                                    <option value="">Selecciona color</option>
                                </select>
                                <input class="catalog-other-field" type="text" id="v-color-otro" maxlength="50" placeholder="Escribe el color" hidden>
                                <small class="field-error"></small>
                            </div>

                            <div class="form-group">
                                <label for="v-transmision">Transmisión <span aria-hidden="true">*</span></label>
                                <select id="v-transmision" required>
                                    <option value="">Selecciona transmisión</option>
                                </select>
                                <input class="catalog-other-field" type="text" id="v-transmision-otro" maxlength="50" placeholder="Escribe la transmisión" hidden>
                                <small class="field-error"></small>
                            </div>

                            <div class="form-group">
                                <label for="v-tipo-factura">Tipo de factura <span aria-hidden="true">*</span></label>
                                <select id="v-tipo-factura" required>
                                    <option value="">Selecciona tipo de factura</option>
                                    <option value="Original">Original</option>
                                    <option value="Refactura">Refactura</option>
                                    <option value="Empresa">Empresa</option>
                                    <option value="Otro">Otro</option>
                                </select>
                                <input class="catalog-other-field" type="text" id="v-tipo-factura-otro" maxlength="50" placeholder="Escribe el tipo de factura" hidden>
                                <small class="field-error"></small>
                            </div>

                            <div class="form-group">
                                <label for="v-propietarios">Número de propietarios <span aria-hidden="true">*</span></label>
                                <select id="v-propietarios" required>
                                    <option value="">Selecciona una opción</option>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                    <option value="5 o más">5 o más</option>
                                </select>
                                <small class="field-error"></small>
                            </div>

                            <div class="form-group">
                                <label for="v-km">Kilometraje <span aria-hidden="true">*</span></label>
                                <div class="input-suffix">
                                    <input type="number" id="v-km" inputmode="numeric" min="0" max="9999999" placeholder="Ej. 52,000" required>
                                    <span>km</span>
                                </div>
                                <small class="field-error"></small>
                            </div>

                            <fieldset class="form-group radio-fieldset">
                                <legend>Refrendo vehicular <span aria-hidden="true">*</span></legend>
                                <div class="radio-options">
                                    <label class="radio-option">
                                        <input type="radio" name="refrendo" value="Al corriente" required>
                                        <span>Al corriente</span>
                                    </label>
                                    <label class="radio-option">
                                        <input type="radio" name="refrendo" value="Con adeudo" required>
                                        <span>Adeudo anual</span>
                                    </label>
                                </div>
                                <div class="conditional-field" id="refrendo-adeudo-wrap" hidden>
                                    <label for="v-refrendo-adeudo">¿Cuánto se adeuda?</label>
                                    <input type="number" id="v-refrendo-adeudo" min="0" step="0.01" placeholder="Monto aproximado">
                                </div>
                                <small class="field-error" data-radio-error="refrendo"></small>
                            </fieldset>

                            <div class="form-group form-group-full">
                                <label for="v-imperfecciones">Imperfecciones (exterior / interior) <span aria-hidden="true">*</span></label>
                                <textarea id="v-imperfecciones" rows="4" placeholder="Describe golpes, rayones, detalles mecánicos o cualquier condición relevante." required></textarea>
                                <small class="field-error"></small>
                            </div>
                        </div>

                        <div class="form-subsection">
                            <h4>¿Dónde se encuentra tu auto?</h4>
                            <div class="form-grid form-grid-two">
                                <div class="form-group">
                                    <label for="v-estado">Estado <span aria-hidden="true">*</span></label>
                                    <select id="v-estado" required>
                                        <option value="">Selecciona estado</option>
                                        <option>Aguascalientes</option>
                                        <option>Baja California</option>
                                        <option>Baja California Sur</option>
                                        <option>Campeche</option>
                                        <option>Chiapas</option>
                                        <option>Chihuahua</option>
                                        <option>Ciudad de México</option>
                                        <option>Coahuila</option>
                                        <option>Colima</option>
                                        <option>Durango</option>
                                        <option>Estado de México</option>
                                        <option>Guanajuato</option>
                                        <option>Guerrero</option>
                                        <option>Hidalgo</option>
                                        <option>Jalisco</option>
                                        <option>Michoacán</option>
                                        <option>Morelos</option>
                                        <option>Nayarit</option>
                                        <option>Nuevo León</option>
                                        <option>Oaxaca</option>
                                        <option>Puebla</option>
                                        <option>Querétaro</option>
                                        <option>Quintana Roo</option>
                                        <option>San Luis Potosí</option>
                                        <option>Sinaloa</option>
                                        <option>Sonora</option>
                                        <option>Tabasco</option>
                                        <option>Tamaulipas</option>
                                        <option>Tlaxcala</option>
                                        <option>Veracruz</option>
                                        <option>Yucatán</option>
                                        <option>Zacatecas</option>
                                    </select>
                                    <small class="field-error"></small>
                                </div>

                                <div class="form-group">
                                    <label for="v-municipio">Municipio <span aria-hidden="true">*</span></label>
                                    <input type="text" id="v-municipio" placeholder="Ingresa municipio" required>
                                    <small class="field-error"></small>
                                </div>
                            </div>
                        </div>

                        <div class="wizard-actions">
                            <button type="button" class="wizard-btn wizard-btn-secondary" data-prev-step="1">
                                <i class="fas fa-arrow-left" aria-hidden="true"></i> Atrás
                            </button>
                            <button type="button" class="wizard-btn wizard-btn-primary" data-next-step="3">
                                Siguiente <i class="fas fa-arrow-right" aria-hidden="true"></i>
                            </button>
                        </div>
                    </section>

                    <section class="wizard-panel" data-step-panel="3" aria-labelledby="step-three-title" hidden>
                        <div class="panel-heading">
                            <span class="panel-number">3</span>
                            <h3 id="step-three-title">Últimos detalles</h3>
                        </div>

                        <div class="final-copy">
                            <p>
                                ¡Listo! El equipo de CARPRIX se pondrá en contacto contigo para continuar
                                con la revisión y el proceso de compra.
                            </p>
                            <p>Ten preparadas estas fotografías de tu vehículo:</p>
                        </div>

                        <ul class="photo-checklist">
                            <li><i class="fas fa-check" aria-hidden="true"></i> Frente del vehículo</li>
                            <li><i class="fas fa-check" aria-hidden="true"></i> Perfil lateral</li>
                            <li><i class="fas fa-check" aria-hidden="true"></i> Llanta / rin</li>
                            <li><i class="fas fa-check" aria-hidden="true"></i> Asientos traseros</li>
                            <li><i class="fas fa-check" aria-hidden="true"></i> Asientos delanteros</li>
                            <li><i class="fas fa-check" aria-hidden="true"></i> Motor</li>
                            <li><i class="fas fa-check" aria-hidden="true"></i> Tablero / interiores</li>
                            <li><i class="fas fa-check" aria-hidden="true"></i> Cajuela</li>
                        </ul>

                        <div class="request-summary" id="request-summary" aria-live="polite"></div>

                        <label class="terms-check">
                            <input type="checkbox" id="v-terminos" required>
                            <span>
                                Confirmo que he leído y acepto los términos y condiciones generales de uso,
                                incluyendo el aviso de privacidad, y autorizo a CARPRIX a utilizar mis datos
                                únicamente para dar seguimiento a esta solicitud.
                            </span>
                        </label>
                        <small class="field-error terms-error" id="terms-error"></small>

                        <div class="wizard-actions">
                            <button type="button" class="wizard-btn wizard-btn-secondary" data-prev-step="2">
                                <i class="fas fa-arrow-left" aria-hidden="true"></i> Atrás
                            </button>
                            <button type="submit" class="wizard-btn wizard-btn-primary" id="btn-vende">
                                Enviar solicitud <i class="fas fa-paper-plane" aria-hidden="true"></i>
                            </button>
                        </div>
                    </section>
                </form>
            </div>
        </section>
    </main>

    <footer class="main-footer">
        <div class="container footer-grid">
            <div class="footer-col">
                <h4 class="footer-title title-green">Conoce más</h4>
                <ul>
                    <li><a href="nosotros.php">¿Quiénes Somos?</a></li>
                    <li><a href="vende.php">Vende tu auto</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4 class="footer-title title-white">Legales</h4>
                <ul><li><a href="#">Aviso de privacidad</a></li></ul>
            </div>
            <div class="footer-col">
                <h4 class="footer-title title-grey">Ayuda</h4>
                <ul>
                    <li><a href="contacto.php">Contacto</a></li>
                    <li><a href="nosotros.php">Preguntas frecuentes</a></li>
                </ul>
            </div>
            <div class="footer-col footer-right">
                <a href="#" class="back-to-home">Regresa al inicio <i class="fas fa-chevron-up"></i></a>
            </div>
        </div>
        <div class="footer-bottom container">
            <a href="../index.php" class="footer-brand-link" aria-label="CARPRIX - Inicio">
                <img src="../img/brand/logo-carprix-dark.svg" alt="CARPRIX - Confianza que te mueve" class="footer-brand-logo">
            </a>
            <p>&copy; <?php echo date('Y'); ?> CARPRIX. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script src="../js/public-operativo-session.js?v=20260810-3"></script>
    <script src="../js/vende.js?v=20260828-1"></script>
</body>
</html>
