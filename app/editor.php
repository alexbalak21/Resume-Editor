<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CV Editor</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" />
<link rel="stylesheet" href="assets/editor.css">
</head>
<body>

<div id="topbar">
    <div id="topbar-left">
        <i class="fa-solid fa-file-pen"></i>
        <span class="brand">CV Editor</span>
    </div>
    <div id="topbar-center">
        <label for="profileSelect">Profil :</label>
        <select id="profileSelect"></select>
        <button id="newProfileBtn" title="Créer un nouveau profil"><i class="fa-solid fa-plus"></i> Nouveau</button>
    </div>
    <div id="topbar-right">
        <span id="saveStatus"></span>
        <button id="previewBtn"><i class="fa-solid fa-print"></i> Aperçu A4 / Imprimer</button>
        <button id="saveBtn"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
    </div>
</div>

<div id="workspace">
    <div id="editorPane">
        <form id="cvForm">

            <details class="section" open>
                <summary><i class="fa-solid fa-id-card"></i> En-tête</summary>
                <div class="section-body">
                    <label>Photo (JPG / PNG / WebP, 1 Mo max)</label>
                    <div id="photoUploadRow">
                        <img id="photoPreview" alt="Aperçu photo">
                        <div id="photoUploadActions">
                            <label for="photoFileInput" class="upload-btn"><i class="fa-solid fa-upload"></i> Choisir une photo</label>
                            <input type="file" id="photoFileInput" accept="image/jpeg,image/png,image/webp" hidden>
                            <button type="button" id="photoRemoveBtn" class="remove-photo-btn"><i class="fa-solid fa-trash"></i> Retirer</button>
                            <span id="photoUploadStatus"></span>
                        </div>
                    </div>
                    <label>Nom complet</label>
                    <input type="text" data-path="header.fullName">
                    <label>Titre / Poste</label>
                    <input type="text" data-path="header.jobTitle">
                    <label>Liens (LinkedIn, site web, GitHub...)</label>
                    <div class="repeat-list" data-list="header.links" data-fields="label,text,url" data-placeholders="Libellé (ex: LinkedIn),Texte affiché,URL"></div>
                    <button type="button" class="add-btn" data-add="header.links">+ Ajouter un lien</button>
                </div>
            </details>

            <details class="section" open>
                <summary><i class="fa-solid fa-user"></i> Profil</summary>
                <div class="section-body">
                    <label>Titre de la section</label>
                    <input type="text" data-path="profile.title">
                    <label>Texte (**gras** supporté)</label>
                    <textarea rows="4" data-path="profile.text"></textarea>
                </div>
            </details>

            <details class="section">
                <summary><i class="fa-solid fa-address-book"></i> Contact</summary>
                <div class="section-body">
                    <label>Titre de la section</label>
                    <input type="text" data-path="contact.title">
                    <label>Éléments</label>
                    <div class="repeat-list" data-list="contact.items" data-fields="label,display,href" data-placeholders="Libellé (ex: Téléphone),Valeur affichée,Lien (tel:.. / mailto:.. optionnel)"></div>
                    <button type="button" class="add-btn" data-add="contact.items">+ Ajouter un contact</button>
                </div>
            </details>

            <details class="section">
                <summary><i class="fa-solid fa-screwdriver-wrench"></i> Compétences</summary>
                <div class="section-body">
                    <label>Titre de la section</label>
                    <input type="text" data-path="skills.title">
                    <label>Liste (une compétence par ligne — [fa:solid:xxx] pour une icône)</label>
                    <div class="repeat-simple" data-list="skills.items" data-placeholder="ex: [fa:solid:database] SQL / MySQL"></div>
                    <button type="button" class="add-btn" data-add="skills.items">+ Ajouter une compétence</button>
                </div>
            </details>

            <details class="section">
                <summary><i class="fa-solid fa-certificate"></i> Certifications</summary>
                <div class="section-body">
                    <label>Titre de la section</label>
                    <input type="text" data-path="certifications.title">
                    <div class="repeat-simple" data-list="certifications.items" data-placeholder="ex: IBM Java Developer"></div>
                    <button type="button" class="add-btn" data-add="certifications.items">+ Ajouter une certification</button>
                </div>
            </details>

            <details class="section">
                <summary><i class="fa-solid fa-language"></i> Langues</summary>
                <div class="section-body">
                    <label>Titre de la section</label>
                    <input type="text" data-path="languages.title">
                    <div class="repeat-list" data-list="languages.items" data-fields="name,level" data-placeholders="Langue (ex: Anglais),Niveau (ex: Courant - C1)"></div>
                    <button type="button" class="add-btn" data-add="languages.items">+ Ajouter une langue</button>
                </div>
            </details>

            <details class="section">
                <summary><i class="fa-solid fa-heart"></i> Centres d'intérêt</summary>
                <div class="section-body">
                    <label>Titre de la section</label>
                    <input type="text" data-path="hobbies.title">
                    <div class="repeat-simple" data-list="hobbies.items" data-placeholder="ex: Escalade / Randonnée"></div>
                    <button type="button" class="add-btn" data-add="hobbies.items">+ Ajouter un intérêt</button>
                </div>
            </details>

            <details class="section">
                <summary><i class="fa-solid fa-briefcase"></i> Expériences professionnelles</summary>
                <div class="section-body">
                    <label>Titre de la section</label>
                    <input type="text" data-path="experience.title">
                    <div class="repeat-timeline" data-list="experience.items"></div>
                    <button type="button" class="add-btn" data-add-timeline="experience.items">+ Ajouter une expérience</button>
                </div>
            </details>

            <details class="section">
                <summary><i class="fa-solid fa-graduation-cap"></i> Formations</summary>
                <div class="section-body">
                    <label>Titre de la section</label>
                    <input type="text" data-path="education.title">
                    <div class="repeat-timeline" data-list="education.items"></div>
                    <button type="button" class="add-btn" data-add-timeline="education.items">+ Ajouter une formation</button>
                </div>
            </details>

        </form>
    </div>

    <div id="previewPane">
        <iframe id="previewFrame" title="Aperçu du CV"></iframe>
    </div>
</div>

<script src="assets/editor.js"></script>
</body>
</html>
