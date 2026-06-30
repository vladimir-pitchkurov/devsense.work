import os
import re

show_keys = {
    'en': {
        'back_to_courses': 'Back to Courses',
        'total_chapters': 'Total Chapters:',
        'total_reward': 'Total Reward:',
        'course_syllabus': 'Course Syllabus',
        'completed': 'Completed',
        'review': 'Review',
        'start_lesson': 'Start Lesson',
    },
    'ru': {
        'back_to_courses': 'Назад к курсам',
        'total_chapters': 'Всего глав:',
        'total_reward': 'Награда:',
        'course_syllabus': 'Силлабус курса',
        'completed': 'Пройдено',
        'review': 'Повторить',
        'start_lesson': 'Начать изучение',
    },
    'ua': {
        'back_to_courses': 'Назад до курсів',
        'total_chapters': 'Всього глав:',
        'total_reward': 'Нагорода:',
        'course_syllabus': 'Силлабус курсу',
        'completed': 'Пройдено',
        'review': 'Повторити',
        'start_lesson': 'Розпочати вивчення',
    },
    'bg': {
        'back_to_courses': 'Назад към курсовете',
        'total_chapters': 'Общо глави:',
        'total_reward': 'Награда:',
        'course_syllabus': 'Учебна програма',
        'completed': 'Завършено',
        'review': 'Преглед',
        'start_lesson': 'Започни урока',
    },
    'de': {
        'back_to_courses': 'Zurück zu den Kursen',
        'total_chapters': 'Gesamtzahl Kapitel:',
        'total_reward': 'Gesamte Belohnung:',
        'course_syllabus': 'Kurslehrplan',
        'completed': 'Abgeschlossen',
        'review': 'Wiederholen',
        'start_lesson': 'Lektion starten',
    },
    'fr': {
        'back_to_courses': 'Retour aux cours',
        'total_chapters': 'Total des chapitres :',
        'total_reward': 'Récompense totale :',
        'course_syllabus': 'Programme du cours',
        'completed': 'Terminé',
        'review': 'Réviser',
        'start_lesson': 'Commencer la leçon',
    },
    'es': {
        'back_to_courses': 'Volver a los cursos',
        'total_chapters': 'Total de capítulos:',
        'total_reward': 'Recompensa total:',
        'course_syllabus': 'Programa del curso',
        'completed': 'Completado',
        'review': 'Repasar',
        'start_lesson': 'Iniciar lección',
    },
    'it': {
        'back_to_courses': 'Torna ai corsi',
        'total_chapters': 'Capitoli totali:',
        'total_reward': 'Premio totale:',
        'course_syllabus': 'Programma del corso',
        'completed': 'Completato',
        'review': 'Rivedi',
        'start_lesson': 'Inizia lezione',
    }
}

base_dir = r"\\wsl$\Ubuntu\home\ubuntu\Development\laravel-playground\lang"

for lang, data in show_keys.items():
    file_path = os.path.join(base_dir, lang, "ui.php")
    if not os.path.exists(file_path):
        continue
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    for k, v in data.items():
        k_pattern = rf"'{re.escape(k)}'\s*=>\s*.*"
        escaped_v = v.replace("'", "\\'")
        if not re.search(k_pattern, content):
            pos = content.find("'courses' => [")
            if pos != -1:
                content = content[:pos+14] + f"\n        '{k}' => '{escaped_v}'," + content[pos+14:]

    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)
print("Added show keys!")
