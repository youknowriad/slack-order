<?php

namespace SlackOrder\Service;

use SlackOrder\Entity\Order;
use SlackOrder\Repository\OrderRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\NoResultException;

class OrderCommandService {

    const ORDER_COMMAND_LIST = 'liste';

    const ORDER_COMMAND_ORDER = 'commande';

    const ORDER_COMMAND_CANCEL = 'annuler';

    const ORDER_COMMAND_HELP = 'help';

    const ORDER_COMMAND_RANDOM = 'aléatoire';

    const ORDER_COMMAND_SEND = 'envoyer';

    /** @var EntityManager $em */
    private $em;

    /** @var \Swift_Mailer */
    private $mailer;

    /** @var \Twig_Environment  */
    private $twig;

    /** @var  String */
    private $orderCommandName;

    /** @var  String */
    private $orderRestaurantName;

    /** @var  String */
    private $orderRestaurantPhoneNumber;

    /** @var  String */
    private $orderStartHour;

    /** @var  String */
    private $orderEndHour;

    /** @var  String */
    private $orderExample;

    /** @var  String */
    private $orderSenderEmail;

    public function __construct(EntityManager $entityManager, \Swift_Mailer $mailer, \Twig_Environment $twig, array $orderConfig)
    {
        $this->em = $entityManager;
        $this->mailer = $mailer;
        $this->twig = $twig;

        $this->orderCommandName = $orderConfig['name'];
        $this->orderRestaurantName = $orderConfig['restaurant']['name'];
        $this->orderRestaurantPhoneNumber = $orderConfig['restaurant']['phone_number'];
        $this->orderRestaurantEmail = $orderConfig['restaurant']['email'];
        $this->orderStartHour = $orderConfig['start_hour'];
        $this->orderEndHour = $orderConfig['end_hour'];
        $this->orderSendByMailActivated = $orderConfig['send_by_mail'];
        $this->orderExample = $orderConfig['example'];
        $this->orderSenderEmail = $orderConfig['sender_email'];
    }

    /**
     * @param $name
     * @param $order
     * @return array
     */
    public function addOrder($name, $order)
    {
        /** @var OrderRepository $orderRepository */
        $orderRepository = $this->em->getRepository('SlackOrder\Entity\Order');

        if (!($this->inTime())) {

            return [
                'text' => sprintf('Désolé les commandes ne sont accéptés que de %s à %s', $this->orderStartHour, $this->orderEndHour),
                'attachments' => [
                    [
                        'fallback' => 'Fail ?',
                        'text' => sprintf('Tu peux quand même appeler %s au %s', $this->orderRestaurantName, $this->orderRestaurantPhoneNumber),
                    ],
                ],
            ];
        }

        $date = new \DateTime();
        $date->setTime(0, 0, 0);
        $orderEntity = $orderRepository->findOneBy(['name' => $name, 'date' => $date]);

        $orderEntity = $orderEntity ? $orderEntity : new Order();

        $orderEntity
            ->setName($name)
            ->setDate($date)
            ->setOrder($order);

        $this->em->persist($orderEntity);
        $this->em->flush();

        return [
            'response_type' => 'in_channel',
            'text' => sprintf('%s a rejoint la commande groupé à midi si tu souhaites en faire de même utilise la commande `%s`', $name, $this->orderCommandName),
            'mrkdwn' => true,
        ];
    }

    /**
     * @param $name
     * @return array
     */
    public function cancelOrder($name)
    {
        /** @var OrderRepository $orderRepository */
        $orderRepository = $this->em->getRepository('SlackOrder\Entity\Order');

        if (!($this->inTime())) {
            return [
                'text' => 'Il est trop tard pour annuler ta commande.',
                'attachments' => [
                    [
                        'fallback' => 'Fail ?',
                        'text' => sprintf('Tu peux quand même essayer d\'appeler %s au %s', $this->orderRestaurantName, $this->orderRestaurantPhoneNumber),
                        'color' => 'danger',
                    ],
                ],
            ];
        }

        $date = new \DateTime();
        $date->setTime(0, 0, 0);
        $orderEntity = $orderRepository->findOneBy(['name' => $name, 'date' => $date]);
        if (null === $orderEntity) {
            return [
                'text' => 'Tu n\'avais rien commandé, mais dans le doute tu as bien fait.',
            ];
        }

        $this->em->remove($orderEntity);
        $this->em->flush();

        return [
            'text' => 'Ta commande a bien été annulée.',
        ];
    }

    /**
     * @return array
     */
    public function orderList()
    {
        /** @var OrderRepository $orderRepository */
        $orderRepository = $this->em->getRepository('SlackOrder\Entity\Order');

        $date = new \DateTime();
        $date->setTime(0, 0, 0);
        /** @var Order[] $orders */
        $orders = $orderRepository->findBy(['date' => $date]);

        if (count($orders) === 0) {
            return [
                'text' => 'Personne n\'a commandé aujourd\'hui',
            ];
        }

        $attachments = [];

        foreach ($orders as $order) {
            $attachment = [
                'fallback' => sprintf('%s a commandé : %s', $order->getName(), $order->getOrder()),
                'text' => sprintf('%s a commandé : %s', $order->getName(), $order->getOrder()),
            ];

            $attachments[] = $attachment;
        }

        return [
            'text' => '*Voici les personnes avec qui tu vas manger :*',
            'mrkdwn' => true,
            'attachments' => $attachments
        ];
    }

    /**
     * @param string $name
     * @param array $params
     * @return array
     */
    public function send($name, $params)
    {
        /** @var OrderRepository $orderRepository */
        $orderRepository = $this->em->getRepository('SlackOrder\Entity\Order');

        try {
            $orderEntity = $orderRepository->getFirstOrderToday();
        }catch (NoResultException $e) {
            return [
                'text' => '*Il n\'y a pas eu de commande aujourd\'hui.*',
            ];
        }

        if ($orderEntity->getName() !== $name) {
            return [
                'text' => sprintf('*Ce n\'est pas toi qui a initié la commande, demande à %s si il peut envoyer la commande.*', $orderEntity->getName()),
            ];
        }

        if ($this->orderSendByMailActivated == false) {
            return [
                'text' => sprintf('*L\'envoie de la commande par email n\'est pas activé merci de passer la commande par téléphone au %s.*', $this->orderRestaurantPhoneNumber),
            ];
        }

        $hour = $params[1];
        if(!preg_match('/^[0-9]{2}:[0-9]{2}$/', $hour)) {
            return [
                'text' => sprintf('*Le format de l\'heure n\'est pas correct (%s)*', $hour),
            ];
        }

        unset($params[1]);
        $phoneNumber = implode('', $params);

        if(!preg_match('/^[0-9]{10}$/', $phoneNumber)) {
            return [
                'text' => sprintf('*Le format du numéro de téléphone n\'est pas correct (%s)*', $phoneNumber),
            ];
        }

        if ($this->sendEmail($hour, $phoneNumber, $name) === 0) {
            return [
                'text' => sprintf('*L\'email n\'a pas été envoyé merci de passer la commande par téléphone au %s.*', $this->orderRestaurantPhoneNumber),
            ];
        }

        return [
            'text' => '*La commande a été envoyée*',
            'attachments' => [
                [
                    'fallback' => 'Fail ?',
                    'text' => sprintf('%s, tu peux quand même confirmer par téléphone au %s ?', ucfirst($orderEntity->getName()), $this->orderRestaurantPhoneNumber),
                    'color' => 'danger',
                ],
            ],
        ];

    }

    /**
     * @return array
     */
    public function help()
    {
        return [
            'text' => '*Tu as faim mais tu ne sais pas comment faire ?*
                - Si tu souhaites passer ou modifier une commande. `'.$this->orderCommandName.' commande '.$this->orderExample.'`
                - Si tu n\'as plus faim. `'.$this->orderCommandName.' annuler`
                - Tu souhaites savoir avec qui tu vas manger ? `'.$this->orderCommandName.' liste`
                - Tu veux envoyer la commande à '.$this->orderRestaurantName.' ? `'.$this->orderCommandName.' envoyer hh:mm 06********`',
            'mrkdwn' => true,
            'attachments' => [
                [
                    'fallback' => 'Fail ?',
                    'text' => sprintf('Important: Tu as jusqu\'à %s pour passer ta commande.', $this->orderEndHour),
                    'color' => 'danger',
                ],
            ],
        ];
    }

    /**
     * @return bool
     */
    private function inTime()
    {
        if(!preg_match('/^[0-9]{2}:[0-9]{2}$/', $this->orderStartHour)) {
            throw new \InvalidArgumentException('Le paramètre de order_star_hour est invalide merci d\'utiliser le format 08:00');
        }
        if(!preg_match('/^[0-9]{2}:[0-9]{2}$/', $this->orderEndHour)) {
            throw new \InvalidArgumentException('Le paramètre de order_end_hour est invalide merci d\'utiliser le format 08:00');
        }
        $currentDate = new \DateTime();
        $startDate = new \DateTime();
        $orderStartHourExploded = explode(':', $this->orderStartHour);
        $startDate->setTime(intval($orderStartHourExploded[0]), intval($orderStartHourExploded[1]), 0);
        $endDate = new \DateTime();
        $orderEndHourExploded = explode(':', $this->orderEndHour);
        $endDate->setTime(intval($orderEndHourExploded[0]), intval($orderEndHourExploded[1]), 0);

        return ($currentDate >= $startDate && $currentDate <= $endDate);
    }

    /**
     * @param String $hour
     * @param String $phoneNumber
     * @param String $name
     * @return int
     */
    private function sendEmail($hour, $phoneNumber, $name)
    {
        /** @var OrderRepository $orderRepository */
        $orderRepository = $this->em->getRepository('SlackOrder\Entity\Order');

        $date = new \DateTime();
        $date->setTime(0, 0, 0);
        /** @var Order[] $orders */
        $orders = $orderRepository->findBy(['date' => $date]);

        $message = \Swift_Message::newInstance()
            ->setSubject('Commande')
            ->setFrom($this->orderSenderEmail)
            ->setTo($this->orderRestaurantEmail)
            ->setBody(
                $this->twig->render(
                    'Emails/order.html.twig',
                    [
                        'name' => $name,
                        'hour' => $hour,
                        'phoneNumber' => $phoneNumber,
                        'orders' => $orders,
                    ]
                ),
                'text/html'
            );

        return $this->mailer->send($message);
    }
}
