import { onMounted, onUnmounted, ref, watch } from 'vue';

export type ChatSource = {
    type: string;
    title: string;
    id: string | null;
    summary: string;
};

export type ChatMetrics = {
    model?: string;
    scope?: string;
    retrieval_time_ms?: number;
    duration_ms?: number;
    prompt_tokens?: number;
    prompt_eval_duration_ms?: number;
    eval_tokens?: number;
    eval_duration_ms?: number;
    eval_tokens_per_sec?: number;
    finish_reason?: string;
    is_truncated?: boolean;
};

export type ChatAttachment = {
    name: string;
    size?: number;
    content: string;
};

export type ChoiceCard = {
    question: string;
    options: string[];
    is_multi_select?: boolean;
};

export type ActionProposal = {
    action: 'create_assessment' | 'update_assessment' | 'delete_assessment';
    section_id: number;
    section_name?: string;
    assessment_id?: number;
    type?: 'activity' | 'laboratory' | 'quiz' | 'exam';
    title: string;
    max_points?: number;
    conducted_on?: string;
    description?: string;
    confirmation_prompt?: string;
    status?: 'pending' | 'executing' | 'executed' | 'dismissed';
    result_message?: string;
    redirect_url?: string;
    error_message?: string;
};

export type ChatMessage = {
    id: string;
    role: 'user' | 'assistant' | 'system';
    content: string;
    timestamp: string;
    attachments?: ChatAttachment[];
    sources?: ChatSource[];
    proposals?: ActionProposal[];
    choices?: ChoiceCard;
    metrics?: ChatMetrics;
    isStreaming?: boolean;
    error?: boolean;
};

export type OllamaModel = {
    name: string;
    model: string;
    size_gb: number;
};

export type AiScope = 'current_section' | 'all_classes' | 'app_help';

export type ConversationSession = {
    id: string;
    title: string;
    scope: AiScope;
    sectionId?: number | null;
    messages: ChatMessage[];
    createdAt: number;
    updatedAt: number;
};

const isAiAssistantOpen = ref(false);
const messages = ref<ChatMessage[]>([]);
const conversations = ref<ConversationSession[]>([]);
const currentConversationId = ref<string | null>(null);
const isSending = ref(false);
const streamingStatusText = ref<string>('');
const availableModels = ref<OllamaModel[]>([]);
const isOllamaOnline = ref<boolean>(false);
const isLocalEndpoint = ref<boolean>(true);
const isAiEnabled = ref<boolean>(true);
const currentScope = ref<AiScope>('current_section');
const activeProfiles = ref<{ chat?: string; code_grading?: string }>({});

const isPullingModel = ref(false);
const pullProgress = ref<{ status: string; completed?: number; total?: number; percent?: number }>({ status: '' });

const ENABLED_KEY = 'classcheck_ai_enabled';
const CONVERSATIONS_KEY = 'classcheck_ai_conversations_v2';
let activeAbortController: AbortController | null = null;

// Initialize enabled state
try {
    const savedEnabled = localStorage.getItem(ENABLED_KEY);
    if (savedEnabled !== null) {
        isAiEnabled.value = savedEnabled === 'true';
    }
} catch {
    // Ignore storage parse errors
}

export function useAiAssistant() {
    const saveConversations = () => {
        try {
            localStorage.setItem(CONVERSATIONS_KEY, JSON.stringify(conversations.value));
        } catch {
            // Ignore storage quota errors
        }
    };

    const startNewConversation = (sectionId?: number | null, scope?: AiScope): ConversationSession => {
        const newSession: ConversationSession = {
            id: 'conv_' + Date.now() + '_' + Math.random().toString(36).substring(2, 7),
            title: 'New Conversation',
            scope: scope || currentScope.value,
            sectionId: sectionId !== undefined ? sectionId : null,
            messages: [],
            createdAt: Date.now(),
            updatedAt: Date.now(),
        };

        conversations.value.unshift(newSession);
        currentConversationId.value = newSession.id;
        messages.value = [];
        if (scope) {
            currentScope.value = scope;
        }
        saveConversations();
        return newSession;
    };

    const loadConversations = (sectionId?: number | null) => {
        try {
            const saved = localStorage.getItem(CONVERSATIONS_KEY);
            if (saved) {
                conversations.value = JSON.parse(saved);
            } else {
                // Backward compatibility migration for legacy single history
                const legacyKey = `classcheck_ai_history_${currentScope.value}_${sectionId || 'global'}`;
                const legacySaved = localStorage.getItem(legacyKey);
                if (legacySaved) {
                    const legacyMsgs: ChatMessage[] = JSON.parse(legacySaved);
                    if (legacyMsgs.length > 0) {
                        const firstUser = legacyMsgs.find((m) => m.role === 'user');
                        const legacyTitle = firstUser ? firstUser.content.slice(0, 45).trim() : 'Previous Session';
                        const session: ConversationSession = {
                            id: 'conv_' + Date.now(),
                            title: legacyTitle,
                            scope: currentScope.value,
                            sectionId: sectionId || null,
                            messages: legacyMsgs,
                            createdAt: Date.now(),
                            updatedAt: Date.now(),
                        };
                        conversations.value = [session];
                        saveConversations();
                    }
                }
            }
        } catch {
            conversations.value = [];
        }

        if (conversations.value.length === 0) {
            startNewConversation(sectionId, currentScope.value);
        } else {
            // Restore active session or pick the first one
            let active = conversations.value.find((c) => c.id === currentConversationId.value);
            if (!active) {
                active = conversations.value[0];
                currentConversationId.value = active.id;
            }
            messages.value = active.messages || [];
            currentScope.value = active.scope || currentScope.value;
        }
    };

    const switchConversation = (conversationId: string) => {
        const session = conversations.value.find((c) => c.id === conversationId);
        if (!session) return;

        currentConversationId.value = session.id;
        messages.value = session.messages || [];
        currentScope.value = session.scope;
    };

    const deleteConversation = (conversationId: string, sectionId?: number | null) => {
        conversations.value = conversations.value.filter((c) => c.id !== conversationId);
        if (currentConversationId.value === conversationId) {
            if (conversations.value.length > 0) {
                switchConversation(conversations.value[0].id);
            } else {
                startNewConversation(sectionId, currentScope.value);
            }
        }
        saveConversations();
    };

    const clearAllConversations = (sectionId?: number | null) => {
        conversations.value = [];
        messages.value = [];
        try {
            localStorage.removeItem(CONVERSATIONS_KEY);
        } catch {
            // Ignore
        }
        startNewConversation(sectionId, currentScope.value);
    };

    const saveHistory = (sectionId?: number | null) => {
        let active = conversations.value.find((c) => c.id === currentConversationId.value);
        if (!active) {
            active = startNewConversation(sectionId, currentScope.value);
        }
        active.messages = messages.value;
        active.updatedAt = Date.now();
        saveConversations();
    };

    const loadHistory = (sectionId?: number | null) => {
        loadConversations(sectionId);
    };

    const setAiEnabled = (enabled: boolean) => {
        isAiEnabled.value = enabled;
        try {
            localStorage.setItem(ENABLED_KEY, String(enabled));
        } catch {
            // Ignore
        }
        if (!enabled) {
            isAiAssistantOpen.value = false;
        }
    };

    const setScope = (scope: AiScope, sectionId?: number | null) => {
        currentScope.value = scope;
        const active = conversations.value.find((c) => c.id === currentConversationId.value);
        if (active) {
            active.scope = scope;
            saveConversations();
        }
    };

    const toggleAssistant = (sectionId?: number | null) => {
        if (!isAiEnabled.value) return;
        isAiAssistantOpen.value = !isAiAssistantOpen.value;
        if (isAiAssistantOpen.value) {
            loadConversations(sectionId);
            warmModel();
        }
    };

    const openAssistant = (sectionId?: number | null) => {
        if (!isAiEnabled.value) return;
        isAiAssistantOpen.value = true;
        loadConversations(sectionId);
        warmModel();
    };

    const closeAssistant = () => {
        isAiAssistantOpen.value = false;
    };

    const clearMessages = (sectionId?: number | null) => {
        messages.value = [];
        const active = conversations.value.find((c) => c.id === currentConversationId.value);
        if (active) {
            active.messages = [];
            active.updatedAt = Date.now();
            saveConversations();
        }
    };

    const fetchStatus = async () => {
        if (!isAiEnabled.value) {
            isOllamaOnline.value = false;
            return;
        }
        try {
            const res = await fetch('/ai-assistant/status', {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (res.ok) {
                const data = await res.json();
                isOllamaOnline.value = Boolean(data.ollama?.online);
                isLocalEndpoint.value = Boolean(data.is_local);
                availableModels.value = data.models || [];
                activeProfiles.value = data.active_profiles || {};
            }
        } catch {
            isOllamaOnline.value = false;
        }
    };

    const warmModel = async () => {
        if (!isAiEnabled.value || !isOllamaOnline.value) return;
        try {
            const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
            await fetch('/ai-assistant/warm', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            });
        } catch {
            // Ignore warming errors
        }
    };

    const stopStreaming = () => {
        if (activeAbortController) {
            activeAbortController.abort();
            activeAbortController = null;
        }
        isSending.value = false;
        streamingStatusText.value = '';
    };

    const sendMessage = async (userPrompt: string, sectionId?: number | null, attachments?: ChatAttachment[]) => {
        const trimmed = userPrompt.trim();
        if ((!trimmed && (!attachments || attachments.length === 0)) || isSending.value || !isAiEnabled.value) return;

        // Clean user message
        const userMsg: ChatMessage = {
            id: 'msg_' + Date.now(),
            role: 'user',
            content: trimmed,
            timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
            attachments: attachments && attachments.length > 0 ? [...attachments] : undefined,
        };

        messages.value.push(userMsg);
        // Update active session title and sync messages
        let session = conversations.value.find((c) => c.id === currentConversationId.value);
        if (!session) {
            session = startNewConversation(sectionId, currentScope.value);
        }
        if (session.title === 'New Conversation' || !session.title) {
            session.title = trimmed.length > 42 ? trimmed.slice(0, 42).trim() + '...' : trimmed;
        }
        session.messages = messages.value;
        session.updatedAt = Date.now();
        saveConversations();

        isSending.value = true;
        streamingStatusText.value = 'Connecting to Octo...';

        const placeholderId = 'msg_' + (Date.now() + 1);
        const assistantMsg: ChatMessage = {
            id: placeholderId,
            role: 'assistant',
            content: '',
            timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
            isStreaming: true,
            sources: [],
        };
        messages.value.push(assistantMsg);

        activeAbortController = new AbortController();

        try {
            const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
            const historyPayload = messages.value
                .filter((m) => m.id !== placeholderId && !m.error)
                .map((m) => ({
                    role: m.role,
                    content: m.content,
                    attachments: m.attachments?.map((a) => ({ name: a.name, content: a.content })),
                }));

            const response = await fetch('/ai-assistant/chat/stream', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/x-ndjson',
                },
                body: JSON.stringify({
                    messages: historyPayload,
                    scope: currentScope.value,
                    section_id: currentScope.value === 'current_section' ? (sectionId || null) : null,
                }),
                signal: activeAbortController.signal,
            });

            if (!response.ok) {
                if (response.status === 419) {
                    throw new Error('Your session expired. Please refresh the page (F5) and try again.');
                }
                if (response.status === 401) {
                    throw new Error('You are not logged in. Please log in to ClassCheck.');
                }
                const errText = await response.text();
                let message = errText;
                try {
                    const parsed = JSON.parse(errText);
                    message = parsed.message || parsed.error || errText;
                } catch {
                    // Raw string
                }
                throw new Error(message || `Server error (${response.status})`);
            }

            const reader = response.body?.getReader();
            if (!reader) {
                throw new Error('Unable to establish stream reader.');
            }

            const decoder = new TextDecoder();
            let buffer = '';

            const processEventLine = (rawLine: string) => {
                try {
                    const event = JSON.parse(rawLine);
                    const target = messages.value.find((m) => m.id === placeholderId) || assistantMsg;

                    if (event.type === 'status') {
                        streamingStatusText.value = event.message || '';
                    } else if (event.type === 'delta') {
                        target.content += event.text || '';
                    } else if (event.type === 'sources') {
                        target.sources = event.sources || [];
                    } else if (event.type === 'proposals') {
                        target.proposals = (event.proposals || []).map((p: any) => ({
                            ...p,
                            status: 'pending',
                        }));
                    } else if (event.type === 'choices') {
                        target.choices = {
                            question: event.question,
                            options: event.options || [],
                            is_multi_select: event.is_multi_select,
                        };
                    } else if (event.type === 'done') {
                        target.metrics = {
                            model: event.model,
                            scope: event.scope,
                            retrieval_time_ms: event.retrieval_time_ms,
                            duration_ms: event.duration_ms,
                            prompt_tokens: event.prompt_tokens,
                            prompt_eval_duration_ms: event.prompt_eval_duration_ms,
                            eval_tokens: event.eval_tokens,
                            eval_duration_ms: event.eval_duration_ms,
                            eval_tokens_per_sec: event.eval_tokens_per_sec,
                            finish_reason: event.finish_reason,
                            is_truncated: event.is_truncated,
                        };
                        target.isStreaming = false;
                    } else if (event.type === 'error') {
                        target.error = true;
                        if (!target.content) {
                            target.content = event.message || 'An error occurred during evaluation.';
                        }
                        target.isStreaming = false;
                    }
                } catch {
                    // Skip malformed JSON line
                }
            };

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop() || '';

                for (const line of lines) {
                    const trimmedLine = line.trim();
                    if (trimmedLine) {
                        processEventLine(trimmedLine);
                    }
                }
            }

            buffer += decoder.decode();
            if (buffer.trim()) {
                const remainingLines = buffer.split('\n');
                for (const line of remainingLines) {
                    const trimmedLine = line.trim();
                    if (trimmedLine) {
                        processEventLine(trimmedLine);
                    }
                }
            }
        } catch (e: any) {
            if (e.name !== 'AbortError') {
                assistantMsg.error = true;
                if (!assistantMsg.content) {
                    assistantMsg.content = 'Connection error: ' + (e.message || 'Could not communicate with Ollama.');
                }
            }
        } finally {
            assistantMsg.isStreaming = false;
            isSending.value = false;
            streamingStatusText.value = '';
            activeAbortController = null;
            saveHistory(sectionId);
        }
    };

    const executeActionProposal = async (proposal: ActionProposal, sectionId?: number | null) => {
        proposal.status = 'executing';
        proposal.error_message = undefined;

        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const res = await fetch('/ai-assistant/actions/execute', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    action: proposal.action,
                    section_id: proposal.section_id,
                    assessment_id: proposal.assessment_id,
                    type: proposal.type,
                    title: proposal.title,
                    max_points: proposal.max_points,
                    conducted_on: proposal.conducted_on,
                    description: proposal.description,
                }),
            });

            const data = await res.json();
            if (res.ok && data.success) {
                proposal.status = 'executed';
                proposal.result_message = data.message;
                proposal.redirect_url = data.redirect_url;
                saveHistory(sectionId);
                return { success: true, data };
            } else {
                throw new Error(data.message || data.error || 'Failed to execute action.');
            }
        } catch (e: any) {
            proposal.status = 'pending';
            proposal.error_message = e.message || 'Action failed.';
            saveHistory(sectionId);
            return { success: false, error: proposal.error_message };
        }
    };

    const dismissProposal = (proposal: ActionProposal, sectionId?: number | null) => {
        proposal.status = 'dismissed';
        saveHistory(sectionId);
    };

    const retryMessage = (messageId: string, sectionId?: number | null) => {
        const index = messages.value.findIndex((m) => m.id === messageId);
        if (index === -1) return;

        let prompt = '';
        if (messages.value[index].role === 'user') {
            prompt = messages.value[index].content;
            messages.value = messages.value.slice(0, index);
        } else if (index > 0 && messages.value[index - 1].role === 'user') {
            prompt = messages.value[index - 1].content;
            messages.value = messages.value.slice(0, index - 1);
        }

        if (prompt) {
            sendMessage(prompt, sectionId);
        }
    };

    const pullModel = async (
        modelName: string = 'hermes3:8b',
        onProgress?: (progress: { status: string; completed?: number; total?: number; percent?: number }) => void,
    ): Promise<boolean> => {
        isPullingModel.value = true;
        pullProgress.value = { status: 'Connecting to Ollama...' };

        try {
            const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
            const response = await fetch('/ai-assistant/pull', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/x-ndjson',
                },
                body: JSON.stringify({ model: modelName }),
            });

            if (!response.ok) {
                const err = await response.text();
                let message = err;
                try {
                    const parsed = JSON.parse(err);
                    message = parsed.message || err;
                } catch {
                    // raw string
                }
                throw new Error(message || 'Failed to download model.');
            }

            const reader = response.body?.getReader();
            if (!reader) {
                throw new Error('Unable to read download stream.');
            }

            const decoder = new TextDecoder();
            let buffer = '';

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop() || '';

                for (const line of lines) {
                    const trimmed = line.trim();
                    if (!trimmed) continue;

                    try {
                        const event = JSON.parse(trimmed);
                        if (event.error) {
                            throw new Error(event.error);
                        }

                        let percent: number | undefined = undefined;
                        if (event.total && event.completed) {
                            percent = Math.min(100, Math.round((event.completed / event.total) * 100));
                        }

                        const pData = {
                            status: event.status || 'Downloading model weights...',
                            completed: event.completed,
                            total: event.total,
                            percent,
                        };
                        pullProgress.value = pData;
                        if (onProgress) {
                            onProgress(pData);
                        }
                    } catch (e: any) {
                        if (e.message && !e.message.includes('JSON')) {
                            throw e;
                        }
                    }
                }
            }

            await fetchStatus();
            isPullingModel.value = false;
            pullProgress.value = { status: 'Model downloaded and ready!', percent: 100 };
            return true;
        } catch (err: any) {
            pullProgress.value = { status: err.message || 'Download failed.' };
            isPullingModel.value = false;
            return false;
        }
    };

    // Keyboard shortcut listener (Ctrl+J or Cmd+J)
    const handleKeydown = (e: KeyboardEvent) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'j') {
            e.preventDefault();
            toggleAssistant();
        }
        if (e.key === 'Escape' && isAiAssistantOpen.value) {
            closeAssistant();
        }
    };

    onMounted(() => {
        window.addEventListener('keydown', handleKeydown);
        if (isAiEnabled.value) {
            fetchStatus();
        }
    });

    onUnmounted(() => {
        window.removeEventListener('keydown', handleKeydown);
        stopStreaming();
    });

    return {
        isAiAssistantOpen,
        isAiEnabled,
        messages,
        conversations,
        currentConversationId,
        isSending,
        streamingStatusText,
        availableModels,
        isOllamaOnline,
        isLocalEndpoint,
        currentScope,
        activeProfiles,
        isPullingModel,
        pullProgress,
        pullModel,
        setAiEnabled,
        setScope,
        toggleAssistant,
        openAssistant,
        closeAssistant,
        clearMessages,
        startNewConversation,
        switchConversation,
        deleteConversation,
        clearAllConversations,
        loadConversations,
        saveConversations,
        loadHistory,
        fetchStatus,
        sendMessage,
        retryMessage,
        executeActionProposal,
        dismissProposal,
        stopStreaming,
    };
}
