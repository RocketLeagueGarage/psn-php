<?php

namespace Tustin\PlayStation\Api\Community;

use Tustin\PlayStation\Client;

use Tustin\PlayStation\Api\AbstractApi;
use Tustin\PlayStation\Api\User;

use Tustin\PlayStation\Resource\Image;

class Community extends AbstractApi 
{
    const COMMUNITY_ENDPOINT    = 'https://communities.api.playstation.com/v1/';
    const SATCHEL_ENDPOINT      = 'https://satchel.api.playstation.com/v1/item/community/%s';

    private $community;
    private $communityId;
    private $members;
    private $threads;

    public function __construct(Client $client, string $communityId) 
    {
        parent::__construct($client);

        $this->communityId = $communityId;
    }

    /**
     * Create a community.
     * 
     * TEMP: Needs to be made static or something.
     *
     * @param string $name The name of the community (must be unique).
     * @param string $type Who can join this community (open/closed)
     * @param string $titleId The titleId of the associated game (leave empty for no association).
     * @return \Tustin\PlayStation\Api\Community\Community
     */
    public function create(string $name, string $type = 'open', string $titleId = '')
    {
        $response = $this->postJson(Community::COMMUNITY_ENDPOINT . 'communities?action=create', [
            'name' => $communityIdOrName,
            'type' => $type,
            'titleId' => $titleId
        ]);
    }

    /**
     * Gets all the information fields of the Community.
     * 
     * @param bool $force Force an API request instead of using info from cache.
     * @return \stdClass
     */
    public function info(bool $force = false) : \stdClass
    {
        if ($this->community === null || $force)
        {
            $this->community = $this->client->get(sprintf(self::COMMUNITY_ENDPOINT . 'communities/%s', $this->communityId), [
                'includeFields' => 'backgroundImage,description,id,isCommon,members,name,profileImage,role,unreadMessageCount,sessions,timezoneUtcOffset,language,titleName',
            ]);
        }

        return $this->community;
    }

    /**
     * Gets the id of the Community.
     * 
     * This is used for searching for the community later on.
     *
     * @return string Community id.
     */
    public function id() : string
    {
        return $this->info()->id;
    }

    /**
     * Gets the name of the Community.
     *
     * @return string Community name.
     */
    public function name() : string
    {
        return $this->info()->name;
    }

    /**
     * Gets the description of the Community.
     *
     * @return string Community description.
     */
    public function description() : string
    {
        return $this->info()->description;
    }

    /**
     * Gets the amount of Users in the Community.
     *
     * @return int
     */
    public function memberCount() : int 
    {
        return $this->info()->members->size;
    }

    /**
     * Sets the associated Game for the Community.
     *
     * @param string $titleId Title ID for the Game.
     * @return void
     */
    public function setGame(string $titleId) : void
    {
        $this->set([
            'titleId' => $titleId
        ]);
    }

    /**
     * Sets the name of the Community.
     *
     * @param string $name New Community name.
     * @return void
     */
    public function setName(string $name) : void
    {
        $this->set([
            'name' => $name
        ]);
    }

    /**
     * Sets the image for the Community.
     *
     * @param string $imageData Raw bytes of the image.
     * @return void
     */
    public function setImage(string $imageData) : void
    {
        $url = $this->uploadImage('communityProfileImage', $imageData);

        $this->set([
            'profileImage' => [
                'sourceUrl' => $url
            ]
        ]);
    }

    /**
     * Sets the background image for the Community.
     *
     * @param string $imageData Raw bytes of the image.
     * @return void
     */
    public function setBackgroundImage(string $imageData) : void
    {
        $url = $this->uploadImage('communityBackgroundImage', $imageData);

        $this->set([
            'backgroundImage' => [
                'sourceUrl' => $url
            ]
        ]);
    }

    /**
     * Sets the background color for the Community.
     *
     * @param int $color RGB value (e.g. 0x000000).
     * @return void
     */
    public function setBackgroundColor(int $color) : void
    {
        $background = $this->info(true)->backgroundImage;

        $this->set([
            'backgroundImage' => [
                'color' => sprintf('%06X', $color),
                'sourceUrl' => $background->sourceUrl ?? ''
            ]
        ]);
    }

    /**
     * Set who can join the community.
     *
     * @param string $status 'open' or 'closed'.
     * @return void
     */
    public function setStatus(string $status) : void
    {
        $this->set([
            'type' => $status
        ]);
    }

    /**
     * Invite one or more Users to the Community.
     *
     * @param array $users Array of each User's onlineId as string.
     * @return void
     */
    public function invite(array $users) : void
    {
        $data = (object)[
            'onlineIds' => $users
        ];

        $this->postJson(sprintf(self::COMMUNITY_ENDPOINT . 'communities/%s/members', $this->id()), $data);
    }

    /**
     * Get the users in the community.
     * 
     * TODO: This API call returns a `next` property, which is the endpoint you should use to get the next 100 users.
     * I just need to find a good way to provide this property to support pagination. Maybe with some kind of callback function? I'm not too sure yet.
     * - Tustin 10/10/2019
     *
     * @param int $limit Amount of \Tustin\PlayStation\Api\User to return.
     * @return array Array of \Tustin\PlayStation\Api\User.
     */
    public function members(int $limit = 100) : array
    {
        if ($limit > 100) {
            throw new \InvalidArgumentException('Limit can only have a maximum value of 100.');
        }

        $returnMembers = [];

        $members = $this->get(sprintf(self::COMMUNITY_ENDPOINT . 'communities/%s/members', $this->id()), [
            'limit' => $limit
        ]);

        if ($members->size === 0) return $returnMembers;

        foreach ($members->members as $member) {
            $returnMembers[] = new User($this->client, $member->onlineId);
        }

        return $returnMembers;
    }

    /**
     * Get all the threads for the community.
     * 
     * This should be used to find the id for the MOTD and Discussion threads. 
     * Typically for each community, the 0th index item will be the MOTD thread and the 1st index will be the Discussion thread.
     *
     * @return array Array of \Tustin\PlayStation\Api\Community\Thread.
     */
    public function threads() : \stdClass
    {
        $returnThreads = [];

        $threads = $this->get(sprintf(self::COMMUNITY_ENDPOINT . 'communities/%s/threads', $this->id()));

        if (count($threads->threads) === 0) return $returnThreads;

        foreach ($threads->threads as $thread) {
            $returnThreads[] = new Thread($this->client, $thread, $this);
        }

        return $returnThreads;
    }

    /**
     * Gets the Game the Community is associated with.
     *
     * @return Game|null
     */
    public function game() : ?Game
    {
        if (!isset($this->info()->titleId)) return null;

        return new Game($this->client, $this->info()->titleId);
    }

    /**
     * Uploads an image to Sony's CDN and returns the URL.
     * 
     * Sony requires all images (profile images, community images, etc) to be set with this CDN URL.
     * 
     * Each image uploaded using this endpoint requires a pupose. Some documented purposes:
     * communityProfileImage, communityBackgroundImage, communityWallImage
     *
     * @param string $purpose The purpose of the image (see above).
     * @param \Tustin\PlayStation\Resource\Image $image The JPEG image.
     * @return string
     */
    private function uploadImage(string $purpose, Image $image) : string
    {
        if ($image->type() !== 'image/jpeg') {
            throw new \InvalidArgumentException("Image file type can only be JPEG.");
        }

        $parameters = [
            [
                'name' => 'purpose',
                'contents' => $purpose,
            ],
            [
                'name' => 'file',
                'filename' => 'dummy_file_name',
                'contents' => $image->data(),
                'headers' => [
                    'Content-Type' => 'image/jpeg'
                ]
            ],
            [
                'name' => 'mimeType',
                'contents' => 'image/jpeg',
            ]
        ];

        $response = $this->postMultiPart(sprintf(self::SATCHEL_ENDPOINT, $this->id()), $parameters);

        return $response->url;
    }

    /**
     * Sets a property on the Community.
     *
     * @param array $postData
     * @return object
     */
    private function set(array $postData)
    {
        return $this->client->putJson(sprintf(self::COMMUNITY_ENDPOINT . 'communities/%s', $this->id()), $postData);
    }
}