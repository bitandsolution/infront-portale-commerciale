/**
 * Runs the router visits of a panel one at a time (DM-07): Inertia cancels a visit when another one
 * starts, so the automatic saves of the choice of the slides and of the slides composed with blocks
 * wait for each other. A task with the same key that has not started yet is replaced by the newer
 * one: only the last content is sent.
 */
export interface VisitQueue {
    /** Queues a visit; the task calls done() when the visit finishes (onFinish). */
    run(key: string, task: (done: () => void) => void): void;
    /** Runs the callback once every queued visit has finished. */
    whenIdle(callback: () => void): void;
    busy(): boolean;
}

export function createVisitQueue(): VisitQueue {
    const pending: Array<{ key: string; task: (done: () => void) => void }> = [];
    let running = false;
    let waiting: Array<() => void> = [];

    function next(): void {
        const item = pending.shift();
        if (item === undefined) {
            running = false;
            const callbacks = waiting;
            waiting = [];
            callbacks.forEach((callback) => callback());

            return;
        }
        running = true;
        let finished = false;
        item.task(() => {
            if (finished) {
                return;
            }
            finished = true;
            next();
        });
    }

    return {
        run(key, task) {
            const index = pending.findIndex((item) => item.key === key);
            if (index >= 0) {
                pending[index] = { key, task };
            } else {
                pending.push({ key, task });
            }
            if (!running) {
                next();
            }
        },
        whenIdle(callback) {
            if (!running && pending.length === 0) {
                callback();
            } else {
                waiting.push(callback);
            }
        },
        busy: () => running || pending.length > 0,
    };
}
