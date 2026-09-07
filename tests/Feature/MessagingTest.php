<?php

namespace Tests\Feature;

use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_contacts_lists_classmates_who_share_a_course_code(): void
    {
        $me      = $this->student('20230099');
        $maya    = $this->student('20230150', 'Maya Karim');
        $outside = $this->student('99', 'Other Uni', 'Some Other University');
        $noShare = $this->student('20230200', 'Different Courses');

        $this->courseFor($me, 'CSC400');
        $this->courseFor($maya, 'CSC400');
        $this->courseFor($outside, 'CSC400');
        $this->courseFor($noShare, 'BIO101');

        $this->actingAs($me, 'sanctum')->getJson('/api/chat/contacts')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.fullName', 'Maya Karim')
            ->assertJsonPath('0.sharedCodes.0', 'CSC400');
    }

    public function test_a_student_can_message_a_classmate(): void
    {
        $me   = $this->student('20230099');
        $maya = $this->student('20230150', 'Maya Karim');
        $this->courseFor($me, 'CSC400');
        $this->courseFor($maya, 'CSC400');

        $this->actingAs($me, 'sanctum')
            ->postJson('/api/chat/messages/'.$maya->id, ['body' => 'Did you finish Lab 6?'])
            ->assertCreated();

        $this->assertDatabaseHas('messages', [
            'sender_id'    => $me->id,
            'recipient_id' => $maya->id,
            'body'         => 'Did you finish Lab 6?',
        ]);
    }

    public function test_a_student_cannot_message_someone_who_shares_no_course(): void
    {
        $me       = $this->student('20230099');
        $stranger = $this->student('20230200', 'Stranger');
        $this->courseFor($me, 'CSC400');
        $this->courseFor($stranger, 'BIO101');

        $this->actingAs($me, 'sanctum')
            ->postJson('/api/chat/messages/'.$stranger->id, ['body' => 'hello?'])
            ->assertForbidden();

        $this->actingAs($me, 'sanctum')
            ->getJson('/api/chat/messages/'.$stranger->id)
            ->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_opening_a_conversation_marks_incoming_messages_as_read(): void
    {
        $me   = $this->student('20230099');
        $maya = $this->student('20230150', 'Maya Karim');
        $this->courseFor($me, 'CSC400');
        $this->courseFor($maya, 'CSC400');

        Message::create(['sender_id' => $maya->id, 'recipient_id' => $me->id, 'body' => 'hey']);

        $this->actingAs($me, 'sanctum')->getJson('/api/chat/contacts')
            ->assertJsonPath('0.unread', 1);

        $this->actingAs($me, 'sanctum')->getJson('/api/chat/messages/'.$maya->id)
            ->assertOk()->assertJsonCount(1);

        $this->actingAs($me, 'sanctum')->getJson('/api/chat/contacts')
            ->assertJsonPath('0.unread', 0);
    }

    public function test_each_course_code_is_a_group_room_with_a_member_count(): void
    {
        $me   = $this->student('20230099');
        $maya = $this->student('20230150', 'Maya Karim');
        $this->courseFor($me, 'CSC400');
        $this->courseFor($maya, 'CSC400');

        $this->actingAs($me, 'sanctum')->getJson('/api/groups')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.code', 'CSC400')
            ->assertJsonPath('0.members', 2);
    }

    public function test_group_messages_are_visible_to_everyone_taking_the_course(): void
    {
        $me   = $this->student('20230099');
        $maya = $this->student('20230150', 'Maya Karim');
        $this->courseFor($me, 'CSC400');
        $this->courseFor($maya, 'CSC400');

        $this->actingAs($me, 'sanctum')
            ->postJson('/api/groups/CSC400/messages', ['body' => 'Is the project due Friday?'])
            ->assertCreated()
            ->assertJsonPath('senderName', 'Test Student');

        $this->actingAs($maya, 'sanctum')->getJson('/api/groups/CSC400/messages')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.body', 'Is the project due Friday?');
    }

    public function test_you_cannot_read_or_post_in_a_group_for_a_course_you_do_not_take(): void
    {
        $me = $this->student('20230099');
        $this->courseFor($me, 'CSC400');

        $this->actingAs($me, 'sanctum')->getJson('/api/groups/BIO101/messages')->assertForbidden();

        $this->actingAs($me, 'sanctum')
            ->postJson('/api/groups/BIO101/messages', ['body' => 'let me in'])
            ->assertForbidden();

        $this->assertDatabaseCount('group_messages', 0);
    }

    public function test_group_rooms_are_scoped_to_one_university(): void
    {
        $me      = $this->student('20230099');
        $outside = $this->student('99', 'Other Uni', 'Some Other University');
        $this->courseFor($me, 'CSC400');
        $this->courseFor($outside, 'CSC400');

        $this->actingAs($outside, 'sanctum')
            ->postJson('/api/groups/CSC400/messages', ['body' => 'from another university'])
            ->assertCreated();

        // Same course code, different university: the message must not leak across
        $this->actingAs($me, 'sanctum')->getJson('/api/groups/CSC400/messages')
            ->assertOk()->assertJsonCount(0);

        $this->actingAs($me, 'sanctum')->getJson('/api/groups')
            ->assertJsonPath('0.members', 1);
    }

    public function test_unread_group_messages_are_counted_until_the_room_is_opened(): void
    {
        $me   = $this->student('20230099');
        $maya = $this->student('20230150', 'Maya Karim');
        $this->courseFor($me, 'CSC400');
        $this->courseFor($maya, 'CSC400');

        $this->actingAs($maya, 'sanctum')->postJson('/api/groups/CSC400/messages', ['body' => 'hello all']);

        $this->actingAs($me, 'sanctum')->getJson('/api/groups')->assertJsonPath('0.unread', 1);
        $this->actingAs($me, 'sanctum')->getJson('/api/groups/CSC400/messages')->assertOk();
        $this->actingAs($me, 'sanctum')->getJson('/api/groups')->assertJsonPath('0.unread', 0);
    }
}
