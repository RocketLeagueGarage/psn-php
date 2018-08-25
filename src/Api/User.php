<?php

namespace PlayStation\Api;

use PlayStation\Client;
use PlayStation\SessionType;

use PlayStation\Api\Trophy;
use PlayStation\Api\Session;
use PlayStation\Api\MessageThread;
use PlayStation\Api\Game;

class User extends AbstractApi 
{
    const USERS_ENDPOINT = 'https://us-prof.np.community.playstation.net/userProfile/v1/users/%s/';

    private $onlineId;
    private $onlineIdParameter;
    private $profile;

    private $sessions = [];

    public function __construct(Client $client, string $onlineId = null) 
    {
        parent::__construct($client);

        $this->onlineId = $onlineId;
        $this->onlineIdParameter = $this->onlineId ?? "me";
    }

    /**
     * Gets online ID.
     *
     * @return void
     */
    public function onlineId() : string
    {
        return $this->info()->onlineId;
    }

    /**
     * Gets the onlineId parameter used for User requests.
     *
     * @return string
     */
    public function onlineIdParameter() : string
    {
        return $this->onlineIdParameter;
    }

    /**
     * Gets profile information.
     *
     * @return object
     */
    public function info() : object
    {
        if ($this->profile === null) {
            $this->profile = $this->get(sprintf(self::USERS_ENDPOINT . 'profile2', $this->onlineIdParameter), [
                'fields' => 'npId,onlineId,accountId,avatarUrls,plus,aboutMe,languagesUsed,trophySummary(@default,progress,earnedTrophies),isOfficiallyVerified,personalDetail(@default,profilePictureUrls),personalDetailSharing,personalDetailSharingRequestMessageFlag,primaryOnlineStatus,presences(@titleInfo,hasBroadcastData),friendRelation,requestMessageFlag,blocking,mutualFriendsCount,following,followerCount,friendsCount,followingUsersCount&avatarSizes=m,xl&profilePictureSizes=m,xl&languagesUsedLanguageSet=set3&psVitaTitleIcon=circled&titleIconSize=s'
            ]);
        }
        return $this->profile->profile;
    }

    /**
     * Gets the about me.
     *
     * @return string
     */
    public function aboutMe() : string
    {
        return $this->info()->aboutMe ?? "";
    }

    /**
     * Checks if logged in User is following the current User.
     *
     * @return boolean
     */
    public function following() : bool
    {
        return $this->info()->following;
    }

    /**
     * Gets the follow count.
     *
     * @return integer
     */
    public function followerCount() : int
    {
        return $this->info()->followerCount;
    }

    /**
     * Checks if the User is verified or not.
     *
     * @return boolean
     */
    public function verified() : bool
    {
        return $this->info()->isOfficiallyVerified;
    }

    /**
     * Gets the User's avatar URL.
     *
     * @return string
     */
    public function avatarUrl() : string
    {
        return $this->info()->avatarUrls[0]->avatarUrl;
    }

    /**
     * Checks if logged in User is friends with the current User.
     *
     * @return boolean
     */
    public function friend() : bool
    {
        return ($this->info()->friendRelation !== 'no');
    }

    /**
     * Checks if logged in User is close friends with the current User.
     *
     * @return boolean
     */
    public function closeFriend() : bool
    {
        return ($this->info()->personalDetailSharing !== 'no');
    }

    /**
     * Add the User to friends list.
     *
     * @param string $requestMessage Message to send with the request.
     * @return void
     */
    public function add(string $requestMessage = null) : void
    {
        if ($this->onlineId() === null) return;

        $data = ($requestMessage === null) ? new \stdClass() : [
            "requestMessage" => $requestMessage
        ];

        $this->postJson(sprintf(self::USERS_ENDPOINT . 'friendList/%s', $this->client->onlineId(), $this->onlineId()), $data);
    }

    /**
     * Remove the User from friends list.
     *
     * @return void
     */
    public function remove() : void
    {
        if ($this->onlineId() === null) return;

        $this->delete(sprintf(self::USERS_ENDPOINT . 'friendList/%s', $this->client->onlineId(), $this->onlineId()));
    }

    /**
     * Block the User.
     *
     * @return void
     */
    public function block() : void
    {
        if ($this->onlineId() === null) return;

        $this->post(sprintf(self::USERS_ENDPOINT . 'blockList/%s', $this->client->onlineId(), $this->onlineId()), null);
    }

    /**
     * Unblock the User.
     *
     * @return void
     */
    public function unblock() : void
    {
        if ($this->onlineId() === null) return;

        $this->delete(sprintf(self::USERS_ENDPOINT . 'blockList/%s', $this->client->onlineId(), $this->onlineId()));
    }

    /**
     * Get the User's friends.
     *
     * @param string $filter Online Filter.
     * @param integer $limit How many users to return.
     * @return array Array of Api\User.
     */
    public function friends($filter = 'online', $limit = 36) : array
    {
        $result = [];

        $friends = $this->get(sprintf(self::USERS_ENDPOINT . 'friends/profiles2', $this->onlineIdParameter()), [
            'fields' => 'onlineId',
            'limit' => $limit,
            'sort' => 'name-onlineId'
        ]);
        
        foreach ($friends->profiles as $friend) {
            $result[] = new self($this->client, $friend->onlineId);
        }

        return $result;
    }

    /**
     * Gets the User's Games played.
     *
     * @return array Array of Api\Game.
     */
    public function games($limit = 100) : array
    {
        $returnGames = [];

        $games = $this->get(sprintf(Game::GAME_ENDPOINT . 'users/%s/titles', $this->onlineIdParameter()), [
            'type'  => 'played',
            'app'   => 'richProfile', // ??
            'sort'  => '-lastPlayedDate',
            'limit' => $limit,
            'iw'    => 240, // Size of game image width
            'ih'    => 240  // Size of game image height
        ]);

        if ($games->size === 0) return $returnGames;

        foreach ($games->titles as $game) {
            $returnGames[] = new Game($this->client, $game->titleId, $this);
        }

        return $returnGames;
    }

    /**
     * Send a Message to the User.
     *
     * @param string $message Message to send.
     * @return Message|null
     */
    public function sendMessage(string $message) : ?Message 
    {
        $thread = $this->messageGroup();

        if ($thread === null) return null;

        return $thread->sendMessage($message);
    }

    /**
     * Send an image Message to the User.
     *
     * @param string $imageContents Raw bytes of the image.
     * @return Message|null
     */
    public function sendImage(string $imageContents) : ?Message
    {
        $thread = $this->messageGroup();
        
        if ($thread === null) return null;

        return $thread->sendImage($imageContents);
    }

    /**
     * Send an audio Message to the User.
     *
     * @param string $audioContents Raw bytes of the audio.
     * @param integer $audioLengthSeconds Length of audio file (in seconds).
     * @return Message|null
     */
    public function sendAudio(string $audioContents, int $audioLengthSeconds) : ?Message
    {
        $thread = $this->messageGroup();
        
        if ($thread === null) return null;

        return $thread->sendAudio($audioContents, $audioLengthSeconds);
    }

    /**
     * Get all MessageThreads with the User.
     *
     * @return array Array of Api\MessageThread.
     */
    public function messageThreads() : array
    {
        $returnThreads = [];

        $threads = $this->get(MessageThread::MESSAGE_THREAD_ENDPOINT . 'users/me/threadIds', [
            'withOnlineIds' => $this->onlineId()
        ]);

        if (empty($threads->threadIds)) return [];

        foreach ($threads->threadIds as $thread) {
            $returnThreads[] = new MessageThread($this->client, $thread->threadId);
        }

        return $returnThreads;
    }

    /**
     * Get MessageThread with just the logged in account and the current User.
     *
     * @return MessageThread|null
     */
    public function privateMessageThread() : ?MessageThread
    {
        $threads = $this->messageThreads();

        if (count($threads) === 0) return null;
        
        foreach ($threads as $thread) {
            if ($thread->memberCount() === 2) {
                return $thread;
            }
        }

        return null;
    }

    /**
     * Get User's party Session.
     *
     * @return Session|null
     */
    public function partySession() : ?Session 
    {
        $sessions = $this->filterSessions(SessionType::Party);
        
        return $sessions[0] ?? null;
    }

    /**
     * Get User's game Session.
     *
     * @return Session|null
     */
    public function gameSession() : ?Session
    {
        $sessions = $this->filterSessions(SessionType::Game);
        
        return $sessions[0] ?? null;
    }

    /**
     * Gets the User's Activity.
     *
     * @return array Array of Api\Story
     */
    public function story(int $page = 0, bool $includeComments = true, int $offset = 0, int $blockSize = 10) : array
    {
        $returnActivity = [];
        $activity = $this->get(sprintf(Story::ACTIVITY_ENDPOINT . 'v2/users/%s/feed/%d', $this->onlineIdParameter, $page), [
            'includeComments' => $includeComments,
            'offset' => $offset,
            'blockSize' => $blockSize
        ]);

        foreach ($activity->feed as $story) {
            // Some stories might just be a collection of similiar stories (like trophy unlocks)
            // These stories can't be interacted with like other stories, so we need to grab the condensed stories
            if (isset($story->condensedStories)) {
                foreach ($story->condensedStories as $condensed) {
                    $returnActivity[] = new Story($this->client, $condensed, $this);
                }
            } else {
                $returnActivity[] = new Story($this->client, $story, $this);
            }
        }

        return $returnActivity;
    }

    /**
     * Get all Communities the User is in.
     *
     * @return array Array of Api\Community.
     */
    public function communities() : array
    {
        $returnCommunities = [];

        $communities = $this->get(Community::COMMUNITY_ENDPOINT . 'communities', [
            'fields' => 'backgroundImage,description,id,isCommon,members,name,profileImage,role,unreadMessageCount,sessions,timezoneUtcOffset,language,titleName',
            'includeFields' => 'gameSessions,timezoneUtcOffset,parties',
            'sort' => 'common',
            'onlineId' => $this->onlineId()
        ]);

        if ($communities->size === 0) return $returnCommunities;
        
        foreach ($communities->communities as $community) {
            $returnCommunities[] = new Community($this->client, $community->id);
        }
        
        return $returnCommunities;

    }

    /**
     * Gets (or creates) the message group with just the logged in account and the current User.
     *
     * @return MessageThread
     */
    private function messageGroup() : MessageThread
    {
        if ($this->onlineId() === null) return null;

        $thread = $this->privateMessageThread();

        if ($thread === null) {
            // If we couldn't find an existing message thread, let's make one.
            
            $data = (object)[
                'threadDetail' => (object)[
                    'threadMembers' => [
                        (object)[
                            'onlineId' => $this->onlineId()
                        ],
                        (object)[
                            'onlineId' => $this->client->onlineId()
                        ]
                    ]
                ]
            ];

            $response = $this->postMultiPart(MessageThread::MESSAGE_THREAD_ENDPOINT . 'threads/', 'threadDetail', json_encode($data, JSON_PRETTY_PRINT), [
                'Content-Type' => 'application/json; charset=utf-8'
            ]);

            $thread = new MessageThread($this->client, $response->threadId);
        }

        return $thread;
    }

    /**
     * Filter User's Sessions by SessionType flag.
     *
     * @param integer $type SessionType flag.
     * @return array Array of Api\Session.
     */
    private function filterSessions(int $type) : array
    {
        $sessions = $this->sessions();
        
        $filteredSession = array_filter($sessions, function($session) use ($type) {
            if ($session->getTitleType() & $type) return $session;
        });

        return $filteredSession;
    }

    /**
     * Gets all the User's active Sessions.
     *
     * @return array Array of Api\Session.
     */
    private function sessions() : array
    {
        if (!empty($sessions)) return $sessions;

        $returnSessions = [];

        $sessions = $this->get(sprintf(Session::SESSION_ENDPOINT, $this->onlineIdParameter), [
            'fields' => '@default,npTitleDetail,npTitleDetail.platform,sessionName,sessionCreateTimestamp,availablePlatforms,members,memberCount,sessionMaxUser',
            'titleIconSize' => 's',
            'npLanguage' => 'en'
        ]);

        if ($sessions->size === 0) return null;

        // Multiple sessions could be used if the user is in a party while playing a game.
        foreach ($sessions->sessions as $session) {
            $returnSessions[] = new Session($this->client, $session);
        }

        $sessions = $returnSessions;

        return $returnSessions;
    }
}