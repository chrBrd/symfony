<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Encoder;

/**
 * A generic encoder factory implementation.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class EncoderFactory implements EncoderFactoryInterface
{
    private $encoders;

    public function __construct(array $encoders)
    {
        $this->encoders = $encoders;
    }

    /**
     * {@inheritdoc}
     */
    public function getEncoder($user)
    {
        $encoderKey = null;

        if ($user instanceof EncoderAwareInterface && (null !== $encoderName = $user->getEncoderName())) {
            if (!array_key_exists($encoderName, $this->encoders)) {
                throw new \RuntimeException(sprintf('The encoder "%s" was not configured.', $encoderName));
            }

            $encoderKey = $encoderName;
        } else {
            foreach ($this->encoders as $class => $encoder) {
                if ((\is_object($user) && $user instanceof $class) || (!\is_object($user) && (is_subclass_of($user, $class) || $user == $class))) {
                    $encoderKey = $class;
                    break;
                }
            }
        }

        if (null === $encoderKey) {
            throw new \RuntimeException(sprintf('No encoder has been configured for account "%s".', \is_object($user) ? \get_class($user) : $user));
        }

        if (!$this->encoders[$encoderKey] instanceof PasswordEncoderInterface) {
            $this->encoders[$encoderKey] = $this->createEncoder($this->encoders[$encoderKey]);
        }

        return $this->encoders[$encoderKey];
    }

    /**
     * Creates the actual encoder instance.
     *
     * @return PasswordEncoderInterface
     *
     * @throws \InvalidArgumentException
     */
    private function createEncoder(array $config)
    {
        if (isset($config['algorithm'])) {
            $config = $this->getEncoderConfigFromAlgorithm($config);
        }
        if (!isset($config['class'])) {
            throw new \InvalidArgumentException(sprintf('"class" must be set in %s.', json_encode($config)));
        }
        if (!isset($config['arguments'])) {
            throw new \InvalidArgumentException(sprintf('"arguments" must be set in %s.', json_encode($config)));
        }

        $reflection = new \ReflectionClass($config['class']);

        return $reflection->newInstanceArgs($config['arguments']);
    }

    private function getEncoderConfigFromAlgorithm($config)
    {
        switch ($config['algorithm']) {
            case 'plaintext':
                return array(
                    'class' => PlaintextPasswordEncoder::class,
                    'arguments' => array($config['ignore_case']),
                );

            case 'pbkdf2':
                return array(
                    'class' => Pbkdf2PasswordEncoder::class,
                    'arguments' => array(
                        $config['hash_algorithm'],
                        $config['encode_as_base64'],
                        $config['iterations'],
                        $config['key_length'],
                    ),
                );

            case 'bcrypt':
                return array(
                    'class' => BCryptPasswordEncoder::class,
                    'arguments' => array($config['cost']),
                );

            case 'argon2i':
                return array(
                    'class' => Argon2iPasswordEncoder::class,
                    'arguments' => array(
                        $config['memory_cost'],
                        $config['time_cost'],
                        $config['threads'],
                    ),
                );
        }

        return array(
            'class' => MessageDigestPasswordEncoder::class,
            'arguments' => array(
                $config['algorithm'],
                $config['encode_as_base64'],
                $config['iterations'],
            ),
        );
    }
}
