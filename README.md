# Slack order

Il est souvent laborieux de passer une commande groupée, c'est pourquoi j'ai décidé de developper cette commande slack

## Comment ça s'installe ?

* Créer la base de données : `.bin/doctrine orm:schema-tool:create`
* Il vous faudra tout  d'abord installer l'application sur un serveur web classique avec une petite base de données et pouvoir appeler l'url en HTTPS
* Ensuite vous devrez configurer votre [commande dans l'interface de Slack](https://my.slack.com/services/new/slash-commands) (les droits admins sont nécessaires)


## Configuration

Renommer le fichier de configuration `config/config.yml.dist` en `config/config.yml` et adapter le à vos besoins.

    - `name` Il s'agit de la commande que vous avez configuré dans Slack. 
        - Exemple: "/bagel" "/pizza"
        
    - `example` L'exemple de commande que l'on peut passer pour aider les utilisateurs 
        - Exemple: "Savoyarde" "4 fromages"
        
    - `restaurant.name` Le nom du restaurant où vous souhaité passer commande.
        - Exemple: "McDo" "Mamamia Pizza"
            
    - `restaurant.phone_number` Le numéro de téléphone du restaurant où vous souhaité passer commande.
        - Exemple: Vraiment ?
                
    - `start_hour` L'heure à laquelle les commandes peuvent commencer.
        - Exemple: "09:00"
            
    - `end_hour` L'heure à laquelle les commandes ne sont plus accéptées.
        - Exemple: "09:10" (Il faut être rapide)
            
    - `send_by_mail` Si vous souhaiter autoriser l'envoi d'un email automatique de la commande
        - Exemple: 0 ou 1
            
    - `restaurant.email` Du coup si vous avez activé l'envoi de l'email vous faut un email
        - Exemple: jean@dupont.fr

    - `sender_email` L'email de votre entreprise pour que le restaurant sache qui a commandé
        - Exemple: contact@entreprise.fr

## Comment ça fonctionne ?

    - Une fois installé et configuré vous aurez ceci :

![alt text](https://www.devexcuses.fr/images/slack-order.jpg "Exemple")

