from __future__ import annotations

from dataclasses import dataclass, field
from datetime import datetime, timezone
from typing import Any


@dataclass
class StudentStatus:
    present: bool = False
    activities: list[dict[str, Any]] = field(default_factory=list)


class ClassroomMonitor:
    def __init__(self) -> None:
        self._students: dict[str, StudentStatus] = {}

    def add_student(self, name: str) -> None:
        if not name:
            raise ValueError("Student name is required")
        if name in self._students:
            raise ValueError(f"Student '{name}' already exists")
        self._students[name] = StudentStatus()

    def mark_present(self, name: str) -> None:
        self._get_student(name).present = True

    def mark_absent(self, name: str) -> None:
        self._get_student(name).present = False

    def record_activity(
        self,
        name: str,
        activity: str,
        *,
        timestamp: datetime | None = None,
        metadata: dict[str, Any] | None = None,
    ) -> None:
        if not activity:
            raise ValueError("Activity is required")
        student = self._get_student(name)
        event_time = timestamp or datetime.now(timezone.utc)
        student.activities.append(
            {
                "student": name,
                "activity": activity,
                "timestamp": event_time.isoformat(),
                "metadata": metadata or {},
            }
        )

    def attendance_report(self) -> dict[str, Any]:
        present_students = sorted(
            name for name, status in self._students.items() if status.present
        )
        absent_students = sorted(
            name for name, status in self._students.items() if not status.present
        )
        return {
            "total_students": len(self._students),
            "present_count": len(present_students),
            "absent_count": len(absent_students),
            "present_students": present_students,
            "absent_students": absent_students,
        }

    def activity_report(self, name: str | None = None) -> list[dict[str, Any]]:
        if name is not None:
            return list(self._get_student(name).activities)

        all_activities: list[dict[str, Any]] = []
        for status in self._students.values():
            all_activities.extend(status.activities)
        return all_activities

    def _get_student(self, name: str) -> StudentStatus:
        if name not in self._students:
            raise KeyError(f"Unknown student '{name}'")
        return self._students[name]
